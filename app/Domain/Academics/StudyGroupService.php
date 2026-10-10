<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\PlacementStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Study groups (rombel) and student placement history.
 *
 * Placement rules that are enforced here rather than by the schema:
 *  - a group must reference an academic year, grade level and optional classroom
 *    all belonging to the active tenant;
 *  - a student may hold only one active placement at a time. The database
 *    enforces this too (generated column + unique index), and the service
 *    translates the driver exception into a validation message.
 */
class StudyGroupService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $guard,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): StudyGroup
    {
        return DB::transaction(function () use ($data, $actor): StudyGroup {
            $group = new StudyGroup($data);
            $group->assignToCurrentSchool();

            $schoolId = (int) $group->school_id;

            $this->guard->assertRelated(
                [$group->academicYear, $group->gradeLevel, $group->classroom, $group->homeroomTeacher],
                $schoolId,
                ['school_id'],
            );

            if ($group->academicYear === null || $group->gradeLevel === null) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Tahun ajaran dan jenjang wajib dipilih.',
                ]);
            }

            $group->save();

            $this->audit->record(
                action: 'study_group.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $group,
                metadata: ['study_group_id' => $group->getKey()],
            );

            return $group;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StudyGroup $group, array $data, User $actor): StudyGroup
    {
        return DB::transaction(function () use ($group, $data, $actor): StudyGroup {
            $group->fill($data);

            $schoolId = (int) $group->school_id;

            $this->guard->assertRelated(
                [$group->academicYear, $group->gradeLevel, $group->classroom, $group->homeroomTeacher],
                $schoolId,
                ['school_id'],
            );

            $group->save();

            $this->audit->record(
                action: 'study_group.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $group,
                metadata: ['study_group_id' => $group->getKey()],
            );

            return $group;
        });
    }

    /**
     * Place a student in a study group, optionally closing the placement they
     * held before so the student ends up in exactly one active group.
     */
    public function placeStudent(
        StudyGroup $group,
        Student $student,
        ?string $startsOn,
        User $actor,
        bool $closePrevious = true,
    ): void {
        DB::transaction(function () use ($group, $student, $startsOn, $actor, $closePrevious): void {
            $this->guard->assertSameSchool((int) $group->school_id, $student, ['school_id']);

            $activePlacements = StudyGroupMemberService::activePlacementsFor($student);

            foreach ($activePlacements as $placement) {
                if ((int) $placement->study_group_id === (int) $group->getKey()) {
                    throw ValidationException::withMessages([
                        'student_id' => 'Siswa sudah berada di rombel ini.',
                    ]);
                }

                if ($closePrevious) {
                    StudyGroupMemberService::closePlacement($placement, now()->toDateString());
                }
            }

            try {
                $group->members()->create([
                    'school_id' => $group->school_id,
                    'student_id' => $student->getKey(),
                    'starts_on' => $startsOn ?? now()->toDateString(),
                    'ends_on' => null,
                    'status' => PlacementStatus::Active,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Lost a race against a concurrent placement for the same student.
                throw ValidationException::withMessages([
                    'student_id' => 'Siswa sudah memiliki penempatan aktif di rombel lain.',
                ]);
            }

            $this->audit->record(
                action: 'study_group.student_placed',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $group,
                metadata: [
                    'study_group_id' => $group->getKey(),
                    'student_id' => $student->getKey(),
                ],
            );
        });
    }

    /**
     * Close an active placement, keeping the row as history.
     */
    public function removeStudent(StudyGroup $group, Student $student, User $actor): void
    {
        DB::transaction(function () use ($group, $student, $actor): void {
            $this->guard->assertSameSchool((int) $group->school_id, $student, ['school_id']);

            $placement = $group->members()
                ->where('student_id', $student->getKey())
                ->where('status', PlacementStatus::Active->value)
                ->first();

            if ($placement === null) {
                throw ValidationException::withMessages([
                    'student_id' => 'Siswa tidak memiliki penempatan aktif di rombel ini.',
                ]);
            }

            StudyGroupMemberService::closePlacement($placement, now()->toDateString());

            $this->audit->record(
                action: 'study_group.student_removed',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $group,
                metadata: [
                    'study_group_id' => $group->getKey(),
                    'student_id' => $student->getKey(),
                ],
            );
        });
    }

    /**
     * Guard used by teaching assignment creation: a group must belong to the
     * active tenant.
     */
    public function assertOwnedByTenant(StudyGroup $group): void
    {
        $this->guard->assertSameSchool($this->guard->activeSchoolId(), $group, ['school_id']);
    }

    public function assertSemesterMatchesYear(?Semester $semester, AcademicYear $year): void
    {
        if ($semester !== null) {
            $this->guard->assertSemesterBelongsToYear($semester, $year);
        }
    }

    public function assertClassroomOwnedByTenant(?Classroom $classroom): void
    {
        if ($classroom !== null) {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $classroom, ['school_id']);
        }
    }

    public function assertEmployeeOwnedByTenant(?Employee $employee): void
    {
        if ($employee !== null) {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $employee, ['school_id']);
        }
    }

    public function assertGradeLevelOwnedByTenant(GradeLevel $gradeLevel): void
    {
        $this->guard->assertSameSchool($this->guard->activeSchoolId(), $gradeLevel, ['school_id']);
    }
}
