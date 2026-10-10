<?php

namespace Tests;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\PlacementStatus;
use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\EmployeeUserLink;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithTenancy
{
    private bool $rolesSeeded = false;

    /**
     * Create a user with an active membership in the school and a global role.
     * Role assignment uses the teams feature, so the team id is set explicitly.
     */
    protected function makeUserWithRole(School $school, string $roleName): User
    {
        $this->seedRolesOnce();

        $user = User::factory()->create();

        $role = Role::findByName($roleName, 'web');

        setPermissionsTeamId($school->getKey());
        $user->assignRole($role);
        setPermissionsTeamId(null);

        SchoolUserMembership::factory()->create([
            'school_id' => $school->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return $user;
    }

    /**
     * Run the baseline role/permission seeder once per test so policies resolve
     * real permissions instead of an empty role.
     */
    protected function seedRolesOnce(): void
    {
        if ($this->rolesSeeded) {
            return;
        }

        (new RoleSeeder)->run();

        $this->refreshPermissionCache();

        $this->rolesSeeded = true;
    }

    /**
     * Act as the user with the tenant context resolved to the school.
     */
    protected function actingInSchool(User $user, School $school): static
    {
        $this->actingAs($user);

        app(CurrentSchool::class)->resolve($user, $school->getKey());

        $this->withSession(['current_school_id' => $school->getKey()]);

        return $this;
    }

    /**
     * Reset the tenant context between requests within the same test.
     */
    protected function clearSchoolContext(): void
    {
        app(CurrentSchool::class)->clear();
    }

    protected function refreshPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Academic year, grade level and study group all belonging to one tenant.
     * Keeps individual tests focused on the rule they are actually testing.
     *
     * @return array{academicYear: AcademicYear, gradeLevel: GradeLevel, studyGroup: StudyGroup}
     */
    protected function makeAcademicContext(School $school, string $groupName = '7A'): array
    {
        $this->seedRolesOnce();

        $academicYear = AcademicYear::factory()
            ->spanning('2026-01-01', '2026-12-31')
            ->create(['school_id' => $school->id]);

        $gradeLevel = GradeLevel::factory()
            ->level('SMP', 7)
            ->create(['school_id' => $school->id]);

        $studyGroup = StudyGroup::factory()
            ->forContext($academicYear, $gradeLevel, $groupName)
            ->create(['school_id' => $school->id]);

        return [
            'academicYear' => $academicYear,
            'gradeLevel' => $gradeLevel,
            'studyGroup' => $studyGroup,
        ];
    }

    /**
     * @return array{semester: Semester, academicYear: AcademicYear, gradeLevel: GradeLevel, studyGroup: StudyGroup, teacher: Employee}
     */
    protected function makeTeachingContext(School $school): array
    {
        $context = $this->makeAcademicContext($school);

        $semester = Semester::factory()
            ->forYear($context['academicYear'])
            ->numbered(1)
            ->create(['school_id' => $school->id]);

        $teacher = Employee::factory()->create([
            'school_id' => $school->id,
            'employment_status' => 'permanent',
        ]);

        return [
            ...$context,
            'semester' => $semester,
            'teacher' => $teacher,
        ];
    }

    /**
     * Teaching context plus a login linked to the teacher employee, so the
     * "assigned teacher only" rule can be exercised end to end.
     *
     * @return array{user: User, employee: Employee, studyGroup: StudyGroup, academicYear: AcademicYear, gradeLevel: GradeLevel, semester: Semester, subject: Subject}
     */
    protected function makeAssignedTeacherContext(School $school, string $role = 'teacher'): array
    {
        $context = $this->makeTeachingContext($school);

        $user = $this->makeUserWithRole($school, $role);

        EmployeeUserLink::factory()->linking($context['teacher'], $user)->create();

        $subject = Subject::factory()->create(['school_id' => $school->id]);

        TeachingAssignment::factory()
            ->forGroup($context['studyGroup'], $subject, $context['teacher'])
            ->create(['school_id' => $school->id]);

        return [
            ...$context,
            'user' => $user,
            'subject' => $subject,
        ];
    }

    /**
     * Place students into a study group as of a date.
     *
     * @param  list<Student>  $students
     */
    protected function placeStudents(
        StudyGroup $group,
        array $students,
        string $startsOn = '2026-07-01',
        ?string $endsOn = null,
        PlacementStatus $status = PlacementStatus::Active,
    ): void {
        foreach ($students as $student) {
            DB::table('study_group_members')->insert([
                'school_id' => $group->school_id,
                'study_group_id' => $group->getKey(),
                'student_id' => $student->getKey(),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => $status->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
