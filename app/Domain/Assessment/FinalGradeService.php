<?php

namespace App\Domain\Assessment;

use App\Domain\Attendance\StudyGroupRoster;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AssessmentStatus;
use App\Enums\FinalGradeStatus;
use App\Enums\GradingPolicyStatus;
use App\Models\Assessment;
use App\Models\FinalGrade;
use App\Models\GradingPolicy;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deterministic final grade calculation (PRD-04).
 *
 * Every number produced here is a pure function of (published raw scores,
 * one specific grading policy version). The policy version and the per-category
 * breakdown are stored on the row, so a published grade can always be
 * explained without re-running the calculation.
 *
 * Design decision pending school approval (PRD-04 prompt): when a student has
 * no scores at all in some weighted category, the weighted average is
 * normalized over the weights that DO have scores, and the used weights are
 * recorded in calculation_detail_json. An alternative rule (treat missing as
 * zero) would punish students for assessments that were never held.
 */
class FinalGradeService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly StudyGroupRoster $roster,
    ) {}

    /**
     * Recompute draft final grades for every roster student of one assignment.
     * Finalized grades are never overwritten by a recalculation.
     *
     * @return array{calculated: int, skipped_finalized: int}
     */
    public function calculateForAssignment(
        TeachingAssignment $assignment,
        GradingPolicy $policy,
        User $actor,
    ): array {
        return DB::transaction(function () use ($assignment, $policy, $actor): array {
            $this->assertPolicyUsable($policy, $assignment);

            $config = $policy->config_json;
            $weights = $config['weights'];
            $rounding = (int) $config['rounding'];
            $bands = $config['grade_bands'];

            $assessments = Assessment::query()
                ->where('teaching_assignment_id', $assignment->getKey())
                ->where('status', AssessmentStatus::Published->value)
                ->with('scores')
                ->get();

            $date = $assignment->semester?->ends_on?->toDateString() ?? now()->toDateString();
            $studentIds = $this->roster->studentIdsOn($assignment->studyGroup, $date);

            $calculated = 0;
            $skipped = 0;

            foreach ($studentIds as $studentId) {
                $existing = FinalGrade::query()
                    ->where('student_id', $studentId)
                    ->where('teaching_assignment_id', $assignment->getKey())
                    ->where('semester_id', $assignment->semester_id)
                    ->first();

                if ($existing !== null && $existing->status === FinalGradeStatus::Finalized) {
                    $skipped++;

                    continue;
                }

                [$numeric, $detail] = $this->computeForStudent($studentId, $assessments, $weights, $rounding, $bands);

                $grade = $existing ?? new FinalGrade([
                    'school_id' => $assignment->school_id,
                    'student_id' => $studentId,
                    'teaching_assignment_id' => $assignment->getKey(),
                    'semester_id' => $assignment->semester_id,
                ]);

                $grade->numeric_score = $numeric;
                $grade->grade_label = $numeric === null ? null : $this->labelFor($numeric, $bands);
                $grade->calculation_version = "{$policy->name} v{$policy->version}";
                $grade->calculation_detail_json = $detail;
                $grade->status = FinalGradeStatus::Draft;
                $grade->save();

                $calculated++;
            }

            $this->audit->record(
                action: 'final_grade.calculated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $assignment,
                metadata: [
                    'teaching_assignment_id' => $assignment->getKey(),
                    'grading_policy_id' => $policy->getKey(),
                    'calculated' => $calculated,
                    'skipped_finalized' => $skipped,
                ],
            );

            return ['calculated' => $calculated, 'skipped_finalized' => $skipped];
        });
    }

    /**
     * Finalize one computed grade; after this a change is a correction.
     */
    public function finalize(FinalGrade $grade, User $actor): FinalGrade
    {
        return DB::transaction(function () use ($grade, $actor): FinalGrade {
            if ($grade->status === FinalGradeStatus::Finalized) {
                return $grade;
            }

            if ($grade->numeric_score === null) {
                throw ValidationException::withMessages([
                    'status' => 'Nilai belum terhitung. Hitung ulang sebelum finalisasi.',
                ]);
            }

            $grade->status = FinalGradeStatus::Finalized;
            $grade->finalized_by = $actor->getKey();
            $grade->finalized_at = now();
            $grade->save();

            $this->audit->record(
                action: 'final_grade.finalized',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $grade,
                metadata: [
                    'final_grade_id' => $grade->getKey(),
                    'student_id' => $grade->student_id,
                    'numeric_score' => $grade->numeric_score,
                    'calculation_version' => $grade->calculation_version,
                ],
            );

            return $grade;
        });
    }

    /**
     * Correct a finalized numeric grade. The previous number goes to the audit
     * log (final_grades has no previous-value column by design: the audit log
     * is the single correction trail for the whole platform).
     */
    public function correct(
        FinalGrade $grade,
        float $numericScore,
        ?string $label,
        string $reason,
        User $actor,
    ): FinalGrade {
        return DB::transaction(function () use ($grade, $numericScore, $label, $reason, $actor): FinalGrade {
            if ($grade->status !== FinalGradeStatus::Finalized) {
                throw ValidationException::withMessages([
                    'numeric_score' => 'Koreksi hanya tersedia untuk nilai yang sudah difinalisasi.',
                ]);
            }

            if ($numericScore < 0 || $numericScore > 100) {
                throw ValidationException::withMessages([
                    'numeric_score' => 'Nilai antara 0 dan 100.',
                ]);
            }

            $previous = $grade->numeric_score;

            $grade->numeric_score = $numericScore;
            $grade->grade_label = $label;
            $grade->corrected_by = $actor->getKey();
            $grade->corrected_at = now();
            $grade->save();

            $this->audit->record(
                action: 'final_grade.corrected',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $grade,
                metadata: [
                    'final_grade_id' => $grade->getKey(),
                    'student_id' => $grade->student_id,
                    'previous_score' => $previous,
                    'new_score' => $numericScore,
                    'reason' => $reason,
                ],
            );

            return $grade;
        });
    }

    /**
     * @param  list<Assessment>  $assessments
     * @param  array<int, float>  $weights
     * @param  list<array{label: string, min: float}>  $bands
     * @return array{0: float|null, 1: array<string, mixed>}
     */
    private function computeForStudent(
        int $studentId,
        iterable $assessments,
        array $weights,
        int $rounding,
        array $bands,
    ): array {
        $categoryPercentages = [];
        $excluded = [];

        foreach ($assessments as $assessment) {
            $categoryId = $assessment->category_id === null ? null : (int) $assessment->category_id;

            if ($categoryId === null || ! isset($weights[$categoryId])) {
                $excluded[] = [
                    'assessment_id' => $assessment->getKey(),
                    'reason' => $categoryId === null ? 'tanpa_kategori' : 'kategori_tidak_diboboti',
                ];

                continue;
            }

            $score = $assessment->scores
                ->first(fn ($row): bool => (int) $row->student_id === $studentId && $row->score !== null);

            if ($score === null) {
                continue;
            }

            $categoryPercentages[$categoryId][] = ((float) $score->score / (float) $assessment->max_score) * 100;
        }

        $usedWeights = [];
        $weighted = 0.0;

        foreach ($weights as $categoryId => $weight) {
            if (! isset($categoryPercentages[$categoryId])) {
                continue;
            }

            $average = array_sum($categoryPercentages[$categoryId]) / count($categoryPercentages[$categoryId]);
            $weighted += $average * $weight;
            $usedWeights[$categoryId] = $weight;
        }

        if ($usedWeights === []) {
            return [null, [
                'categories' => [],
                'weights_used' => [],
                'excluded_assessments' => $excluded,
                'rounding' => $rounding,
            ]];
        }

        $numeric = round($weighted / array_sum($usedWeights), $rounding);

        return [$numeric, [
            'categories' => $categoryPercentages,
            'weights_used' => $usedWeights,
            'excluded_assessments' => $excluded,
            'rounding' => $rounding,
        ]];
    }

    /**
     * @param  list<array{label: string, min: float}>  $bands
     */
    private function labelFor(float $numeric, array $bands): ?string
    {
        foreach ($bands as $band) {
            if ($numeric >= $band['min']) {
                return $band['label'];
            }
        }

        return null;
    }

    private function assertPolicyUsable(GradingPolicy $policy, TeachingAssignment $assignment): void
    {
        if ((int) $policy->school_id !== (int) $assignment->school_id) {
            throw ValidationException::withMessages([
                'grading_policy_id' => 'Kebijakan penilaian berasal dari sekolah lain.',
            ]);
        }

        if ($policy->status !== GradingPolicyStatus::Active) {
            throw ValidationException::withMessages([
                'grading_policy_id' => 'Hanya kebijakan berstatus aktif yang dapat dipakai menghitung.',
            ]);
        }

        $semesterYear = $assignment->semester?->academic_year_id;

        if ($semesterYear === null || (int) $semesterYear !== (int) $policy->academic_year_id) {
            throw ValidationException::withMessages([
                'grading_policy_id' => 'Kebijakan berlaku untuk tahun ajaran lain.',
            ]);
        }
    }
}
