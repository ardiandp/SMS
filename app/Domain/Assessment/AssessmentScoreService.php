<?php

namespace App\Domain\Assessment;

use App\Domain\Attendance\StudyGroupRoster;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Raw score entry and the correction trail (PRD-04).
 *
 * Raw scores (assessment_scores) are deliberately kept apart from computed
 * results (final_grades): a policy change can then be re-run without losing
 * what the teacher originally entered.
 *
 * While the assessment is a draft, scores are a plain upsert keyed by student.
 * After publication every change becomes a correction that stores the previous
 * value, who changed it and when.
 */
class AssessmentScoreService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly StudyGroupRoster $roster,
    ) {}

    public function hasAnyScore(Assessment $assessment): bool
    {
        return $assessment->scores()->whereNotNull('score')->exists();
    }

    /**
     * Upsert draft scores for students on the assignment's roster.
     *
     * Keys of $scoresByStudentId must already be validated to belong to the
     * roster (see EnterAssessmentScoresRequest), so a crafted id from another
     * class cannot be scored through this endpoint.
     *
     * @param  array<int, array{score: float|null, feedback?: string|null}>  $scoresByStudentId
     * @return int number of score rows written
     */
    public function saveDraftScores(Assessment $assessment, array $scoresByStudentId, User $actor): int
    {
        return DB::transaction(function () use ($assessment, $scoresByStudentId, $actor): int {
            $this->assertDraft($assessment);

            $written = 0;

            foreach ($scoresByStudentId as $studentId => $entry) {
                $score = $assessment->scores()
                    ->where('student_id', $studentId)
                    ->first();

                $score ??= new AssessmentScore([
                    'school_id' => $assessment->school_id,
                    'assessment_id' => $assessment->getKey(),
                    'student_id' => $studentId,
                ]);

                $score->score = $entry['score'];
                $score->feedback = $entry['feedback'] ?? $score->feedback;
                $score->entered_by = $actor->getKey();
                $score->save();

                $written++;
            }

            $this->audit->record(
                action: 'assessment.scores_saved',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $assessment,
                metadata: [
                    'assessment_id' => $assessment->getKey(),
                    'count' => $written,
                ],
            );

            return $written;
        });
    }

    /**
     * Mark every entered score of an assessment as finalized. Called by
     * AssessmentService::publish inside the same transaction.
     */
    public function finalizeScores(Assessment $assessment): void
    {
        $assessment->scores()
            ->whereNotNull('score')
            ->whereNull('finalized_at')
            ->update(['finalized_at' => now()]);
    }

    /**
     * Correct a score after publication. Keeps actor, time, old value and
     * increments the correction counter — the audit trail PRD-04 requires.
     */
    public function correctScore(
        AssessmentScore $score,
        ?float $newScore,
        string $reason,
        User $actor,
    ): AssessmentScore {
        return DB::transaction(function () use ($score, $newScore, $reason, $actor): AssessmentScore {
            $assessment = $score->assessment;

            if ($assessment === null || $assessment->status !== AssessmentStatus::Published) {
                throw ValidationException::withMessages([
                    'score' => 'Koreksi hanya tersedia untuk nilai asesmen yang sudah diterbitkan.',
                ]);
            }

            if ($newScore !== null && $newScore > (float) $assessment->max_score) {
                throw ValidationException::withMessages([
                    'score' => 'Nilai melebihi skor maksimum asesmen.',
                ]);
            }

            $oldScore = $score->score;

            $score->previous_score = $oldScore;
            $score->score = $newScore;
            $score->corrected_by = $actor->getKey();
            $score->corrected_at = now();
            $score->correction_count = $score->correction_count + 1;
            $score->entered_by = $actor->getKey();
            $score->save();

            $this->audit->record(
                action: 'assessment.score_corrected',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $score,
                metadata: [
                    'assessment_score_id' => $score->getKey(),
                    'assessment_id' => $score->assessment_id,
                    'student_id' => $score->student_id,
                    'previous_score' => $oldScore,
                    'new_score' => $newScore,
                    'reason' => $reason,
                ],
            );

            return $score;
        });
    }

    /**
     * The roster of the assignment's study group on the assessment date —
     * the only students whose scores may be entered.
     *
     * @return list<int>
     */
    public function eligibleStudentIds(Assessment $assessment): array
    {
        $group = $assessment->teachingAssignment?->studyGroup;

        if ($group === null) {
            return [];
        }

        return $this->roster->studentIdsOn($group, $assessment->assessment_date->toDateString());
    }

    private function assertDraft(Assessment $assessment): void
    {
        if ($assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Asesmen sudah diterbitkan. Perubahan nilai hanya melalui koreksi.',
            ]);
        }
    }
}
