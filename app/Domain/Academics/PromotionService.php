<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\GraduationStatus;
use App\Enums\PromotionOutcome;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\GraduationRecord;
use App\Models\PromotionDecision;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Promotion decisions and graduation records (PRD-04).
 *
 * The application deliberately does NOT derive these outcomes from grades:
 * every school has its own approved promotion/graduation rule, and inventing
 * one here would produce unofficial academic records. Instead, an authorized
 * role records the school's own deliberation outcome; this service only
 * enforces tenant consistency, structural coherence (e.g. "naik kelas" needs a
 * target grade level), one-row-per-student-per-year, and a full audit trail.
 */
class PromotionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $tenantGuard,
    ) {}

    /**
     * Record (or revise) the promotion decision for one student/year.
     *
     * A revision updates the single row for that student/year; the previous
     * value is preserved in the audit log, not overwritten silently.
     */
    public function recordDecision(
        Student $student,
        AcademicYear $year,
        PromotionOutcome $outcome,
        ?GradeLevel $fromLevel,
        ?GradeLevel $toLevel,
        ?string $notes,
        User $actor,
    ): PromotionDecision {
        return DB::transaction(function () use ($student, $year, $outcome, $fromLevel, $toLevel, $notes, $actor): PromotionDecision {
            $schoolId = (int) $student->school_id;

            if ((int) $year->school_id !== $schoolId) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Tahun ajaran bukan bagian dari sekolah siswa ini.',
                ]);
            }

            $this->tenantGuard->assertRelated([$fromLevel, $toLevel], (string) $schoolId, ['school_id']);

            if ($outcome !== PromotionOutcome::Graduated && $toLevel === null) {
                throw ValidationException::withMessages([
                    'to_grade_level_id' => 'Keputusan naik kelas atau tinggal kelas memerlukan kelas tujuan.',
                ]);
            }

            if ($outcome === PromotionOutcome::Graduated && $toLevel !== null) {
                throw ValidationException::withMessages([
                    'to_grade_level_id' => 'Keputusan lulus tidak memiliki kelas tujuan.',
                ]);
            }

            $existing = PromotionDecision::query()
                ->where('student_id', $student->getKey())
                ->where('academic_year_id', $year->getKey())
                ->first();

            $previousDecision = $existing?->decision;

            $decision = $existing ?? new PromotionDecision([
                'school_id' => $schoolId,
                'student_id' => $student->getKey(),
                'academic_year_id' => $year->getKey(),
            ]);

            $decision->forceFill([
                'from_grade_level_id' => $fromLevel?->getKey(),
                'to_grade_level_id' => $toLevel?->getKey(),
                'decision' => $outcome,
                'notes' => $notes,
                'decided_by' => $actor->getKey(),
                'decided_at' => now(),
            ])->save();

            $this->audit->record(
                action: $previousDecision === null ? 'promotion_decision.recorded' : 'promotion_decision.revised',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $decision,
                metadata: [
                    'promotion_decision_id' => $decision->getKey(),
                    'student_id' => $student->getKey(),
                    'academic_year_id' => $year->getKey(),
                    'decision' => $outcome->value,
                    'previous_decision' => $previousDecision?->value,
                ],
            );

            return $decision;
        });
    }

    /**
     * Issue the graduation declaration for one student/year.
     *
     * The unique (student_id, academic_year_id) index means a repeat issue for
     * the same student/year is a replacement of the document number, and the
     * previous number is preserved in the audit log.
     */
    public function issueGraduation(
        Student $student,
        AcademicYear $year,
        string $documentNumber,
        User $actor,
    ): GraduationRecord {
        return DB::transaction(function () use ($student, $year, $documentNumber, $actor): GraduationRecord {
            if ((int) $year->school_id !== (int) $student->school_id) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Tahun ajaran bukan bagian dari sekolah siswa ini.',
                ]);
            }

            $existing = GraduationRecord::query()
                ->where('student_id', $student->getKey())
                ->where('academic_year_id', $year->getKey())
                ->first();

            $previousNumber = $existing?->document_number;

            $record = $existing ?? new GraduationRecord([
                'school_id' => (int) $student->school_id,
                'student_id' => $student->getKey(),
                'academic_year_id' => $year->getKey(),
            ]);

            try {
                $record->forceFill([
                    'status' => GraduationStatus::Issued,
                    'document_number' => $documentNumber,
                    'issued_at' => now(),
                    'issued_by' => $actor->getKey(),
                ])->save();
            } catch (QueryException $exception) {
                if (str_contains($exception->getMessage(), 'Duplicate entry')) {
                    throw ValidationException::withMessages([
                        'student_id' => 'Catatan kelulusan untuk siswa dan tahun ajaran ini sudah ada.',
                    ]);
                }

                throw $exception;
            }

            $this->audit->record(
                action: $previousNumber === null ? 'graduation_record.issued' : 'graduation_record.reissued',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $record,
                metadata: [
                    'graduation_record_id' => $record->getKey(),
                    'student_id' => $student->getKey(),
                    'academic_year_id' => $year->getKey(),
                    'previous_document_number' => $previousNumber,
                ],
            );

            return $record;
        });
    }

    /**
     * Decisions recorded for one academic year.
     *
     * @return Collection<int, PromotionDecision>
     */
    public function decisionsForYear(AcademicYear $year): Collection
    {
        return PromotionDecision::query()
            ->where('academic_year_id', $year->getKey())
            ->with(['student', 'fromGradeLevel', 'toGradeLevel', 'decider'])
            ->orderByDesc('decided_at')
            ->get();
    }

    /**
     * Graduation records for one academic year.
     *
     * @return Collection<int, GraduationRecord>
     */
    public function graduationsForYear(AcademicYear $year): Collection
    {
        return GraduationRecord::query()
            ->where('academic_year_id', $year->getKey())
            ->with(['student', 'issuer'])
            ->orderBy('status')
            ->get();
    }
}
