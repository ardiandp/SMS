<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teaching assignments connect a teacher, a subject, a study group and a period.
 *
 * The unique index covers (study_group, subject, employee, semester). Whole-year
 * assignments with a NULL semester are not covered by that index because SQL
 * treats NULLs as distinct, so those are checked explicitly here.
 */
class TeachingAssignmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $guard,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): TeachingAssignment
    {
        return DB::transaction(function () use ($data, $actor): TeachingAssignment {
            $assignment = new TeachingAssignment($data);
            $assignment->assignToCurrentSchool();

            $schoolId = (int) $assignment->school_id;

            $related = [
                $assignment->studyGroup,
                $assignment->subject,
                $assignment->employee,
                $assignment->semester,
            ];

            $this->guard->assertRelated($related, $schoolId, ['school_id']);

            if ($assignment->studyGroup === null
                || $assignment->subject === null
                || $assignment->employee === null) {
                throw ValidationException::withMessages([
                    'study_group_id' => 'Rombel, mata pelajaran, dan guru wajib dipilih.',
                ]);
            }

            if ($assignment->semester !== null) {
                $this->guard->assertSemesterBelongsToYear(
                    $assignment->semester,
                    $assignment->studyGroup->academicYear,
                );
            }

            $this->assertNoDuplicate($assignment);

            try {
                $assignment->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Penugasan guru untuk mapel dan semester ini sudah ada.',
                ]);
            }

            $this->audit->record(
                action: 'teaching_assignment.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $assignment,
                metadata: ['teaching_assignment_id' => $assignment->getKey()],
            );

            return $assignment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TeachingAssignment $assignment, array $data, User $actor): TeachingAssignment
    {
        return DB::transaction(function () use ($assignment, $data, $actor): TeachingAssignment {
            $assignment->fill($data);

            $schoolId = (int) $assignment->school_id;

            $this->guard->assertRelated(
                [$assignment->studyGroup, $assignment->subject, $assignment->employee, $assignment->semester],
                $schoolId,
                ['school_id'],
            );

            $this->assertNoDuplicate($assignment);

            try {
                $assignment->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Penugasan guru untuk mapel dan semester ini sudah ada.',
                ]);
            }

            $this->audit->record(
                action: 'teaching_assignment.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $assignment,
                metadata: ['teaching_assignment_id' => $assignment->getKey()],
            );

            return $assignment;
        });
    }

    /**
     * A teacher may not hold the same subject in the same group twice.
     */
    private function assertNoDuplicate(TeachingAssignment $assignment): void
    {
        $duplicate = TeachingAssignment::query()
            ->where('study_group_id', $assignment->study_group_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('employee_id', $assignment->employee_id)
            ->when(
                $assignment->semester_id === null,
                fn ($query) => $query->whereNull('semester_id'),
                fn ($query) => $query->where('semester_id', $assignment->semester_id),
            )
            ->when(
                $assignment->exists,
                fn ($query) => $query->whereKeyNot($assignment->getKey()),
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'employee_id' => 'Guru sudah ditugaskan mengampu mapel ini di rombel tersebut.',
            ]);
        }
    }

    public function assertOwnedByTenant(StudyGroup $group, Subject $subject, Employee $employee, ?Semester $semester): void
    {
        $schoolId = $this->guard->activeSchoolId();

        $this->guard->assertRelated([$group, $subject, $employee, $semester], $schoolId, ['school_id']);
    }
}
