<?php

namespace App\Domain\Assessment;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\FinalGradeStatus;
use App\Enums\ReportCardStatus;
use App\Models\AcademicYear;
use App\Models\FinalGrade;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Report card publication and revision (PRD-04).
 *
 * Publishing snapshots every finalized final grade into report_card_items:
 * the snapshot is never recomputed, so a later policy change or score
 * correction cannot rewrite what the school already published. A revision
 * creates a NEW row with revision_no + 1 and a fresh snapshot; earlier
 * publications stay readable.
 */
class ReportCardService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * Publish a report card (or a new revision of one) for a student/semester.
     */
    public function publish(
        Student $student,
        AcademicYear $year,
        Semester $semester,
        User $actor,
    ): ReportCard {
        return DB::transaction(function () use ($student, $year, $semester, $actor): ReportCard {
            if ((int) $semester->academic_year_id !== (int) $year->getKey()) {
                throw ValidationException::withMessages([
                    'semester_id' => 'Semester bukan bagian dari tahun ajaran yang dipilih.',
                ]);
            }

            $grades = FinalGrade::query()
                ->where('student_id', $student->getKey())
                ->where('semester_id', $semester->getKey())
                ->where('status', FinalGradeStatus::Finalized->value)
                ->with(['teachingAssignment.subject', 'teachingAssignment.employee'])
                ->get();

            if ($grades->isEmpty()) {
                throw ValidationException::withMessages([
                    'student_id' => 'Belum ada nilai akhir yang difinalisasi untuk semester ini.',
                ]);
            }

            $lastRevision = (int) ReportCard::query()
                ->where('student_id', $student->getKey())
                ->where('academic_year_id', $year->getKey())
                ->where('semester_id', $semester->getKey())
                ->max('revision_no');

            $card = new ReportCard([
                'school_id' => $student->school_id,
                'student_id' => $student->getKey(),
                'academic_year_id' => $year->getKey(),
                'semester_id' => $semester->getKey(),
                'status' => ReportCardStatus::Published,
                'published_at' => now(),
                'published_by' => $actor->getKey(),
                'revision_no' => $lastRevision + 1,
            ]);
            $card->save();

            foreach ($grades as $grade) {
                $assignment = $grade->teachingAssignment;

                $card->items()->create([
                    'school_id' => $card->school_id,
                    'subject_id' => $assignment?->subject_id,
                    'teacher_id' => $assignment?->employee_id,
                    'final_grade_snapshot_json' => [
                        'numeric_score' => $grade->numeric_score,
                        'grade_label' => $grade->grade_label,
                        'calculation_version' => $grade->calculation_version,
                        'final_grade_id' => $grade->getKey(),
                        'finalized_at' => $grade->finalized_at?->toIso8601String(),
                    ],
                ]);
            }

            $this->audit->record(
                action: $lastRevision === 0 ? 'report_card.published' : 'report_card.revised',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $card,
                metadata: [
                    'report_card_id' => $card->getKey(),
                    'student_id' => $student->getKey(),
                    'revision_no' => $card->revision_no,
                    'items' => $card->items()->count(),
                ],
            );

            return $card->load('items');
        });
    }

    /**
     * All published revisions for a student/semester, newest first.
     *
     * @return Collection<int, ReportCard>
     */
    public function revisions(Student $student, AcademicYear $year, Semester $semester)
    {
        return ReportCard::query()
            ->where('student_id', $student->getKey())
            ->where('academic_year_id', $year->getKey())
            ->where('semester_id', $semester->getKey())
            ->orderByDesc('revision_no')
            ->get();
    }
}
