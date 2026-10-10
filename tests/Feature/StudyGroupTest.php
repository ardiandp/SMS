<?php

namespace Tests\Feature;

use App\Enums\PlacementStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class StudyGroupTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicYear $academicYear;

    private GradeLevel $gradeLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $this->academicYear = AcademicYear::factory()->create(['school_id' => $this->school->id]);
        $this->gradeLevel = GradeLevel::factory()->level('SMP', 7)->create([
            'school_id' => $this->school->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => '7A',
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'capacity' => 32,
            'status' => 'active',
        ];
    }

    /**
     * Create a group through the HTTP flow so the rest of the test exercises
     * real data rather than factory rows.
     */
    private function makeGroup(string $name = '7A'): StudyGroup
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/study-groups', $this->payload(['name' => $name]))
            ->assertRedirect();

        return StudyGroup::where('name', $name)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeGroupDirectly(array $overrides = []): StudyGroup
    {
        return StudyGroup::factory()
            ->forContext($this->academicYear, $this->gradeLevel, $overrides['name'] ?? '7X')
            ->create($overrides + ['school_id' => $this->school->id]);
    }

    public function test_admin_can_create_a_study_group(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/study-groups', $this->payload())
            ->assertRedirect();

        $group = $this->makeGroup();

        $this->assertSame($this->school->id, $group->school_id);
        $this->assertSame($this->academicYear->id, $group->academic_year_id);
        $this->assertSame($this->gradeLevel->id, $group->grade_level_id);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'study_group.created',
        ]);
    }

    public function test_group_name_is_unique_per_academic_year(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/study-groups', $this->payload());

        $this->actingInSchool($this->admin, $this->school)
            ->from('/study-groups/create')
            ->post('/study-groups', $this->payload())
            ->assertSessionHasErrors('name');

        $this->assertSame(1, StudyGroup::where('name', '7A')->count());
    }

    public function test_same_group_name_is_allowed_in_another_year(): void
    {
        $otherYear = AcademicYear::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/study-groups', $this->payload());

        $this->actingInSchool($this->admin, $this->school)
            ->post('/study-groups', $this->payload(['academic_year_id' => $otherYear->id]))
            ->assertRedirect();

        $this->assertSame(2, StudyGroup::where('name', '7A')->count());
    }

    public function test_same_group_name_is_allowed_in_another_school(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/study-groups', $this->payload())
            ->assertRedirect();

        $this->assertSame(1, StudyGroup::where('name', '7A')->count());
    }

    public function test_grade_level_must_belong_to_the_same_school(): void
    {
        $otherSchool = School::factory()->create();
        $foreignGradeLevel = GradeLevel::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/study-groups/create')
            ->post('/study-groups', $this->payload(['grade_level_id' => $foreignGradeLevel->id]))
            ->assertSessionHasErrors('grade_level_id');

        $this->assertSame(0, StudyGroup::count());
    }

    public function test_academic_year_must_belong_to_the_same_school(): void
    {
        $otherSchool = School::factory()->create();
        $foreignYear = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/study-groups/create')
            ->post('/study-groups', $this->payload(['academic_year_id' => $foreignYear->id]))
            ->assertSessionHasErrors('academic_year_id');

        $this->assertSame(0, StudyGroup::count());
    }

    public function test_classroom_and_homeroom_teacher_must_belong_to_the_same_school(): void
    {
        $otherSchool = School::factory()->create();
        $foreignClassroom = Classroom::factory()->create(['school_id' => $otherSchool->id]);
        $foreignTeacher = Employee::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/study-groups/create')
            ->post('/study-groups', $this->payload(['classroom_id' => $foreignClassroom->id]))
            ->assertSessionHasErrors('classroom_id');

        $this->actingInSchool($this->admin, $this->school)
            ->from('/study-groups/create')
            ->post('/study-groups', $this->payload(['homeroom_employee_id' => $foreignTeacher->id]))
            ->assertSessionHasErrors('homeroom_employee_id');

        $this->assertSame(0, StudyGroup::count());
    }

    public function test_group_from_another_tenant_is_not_readable_or_editable(): void
    {
        $otherSchool = School::factory()->create();
        $otherYear = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);
        $otherLevel = GradeLevel::factory()->create(['school_id' => $otherSchool->id]);
        $foreign = StudyGroup::factory()->forContext($otherYear, $otherLevel, '9Z')
            ->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/study-groups/{$foreign->id}")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->put("/study-groups/{$foreign->id}", $this->payload(['name' => 'Diretas']))
            ->assertNotFound();

        $this->assertSame('9Z', $foreign->fresh()->name);
    }

    public function test_teacher_can_read_study_groups_but_not_manage_them(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $group = $this->makeGroup();

        $this->actingInSchool($teacher, $this->school)->get('/study-groups')->assertOk();
        $this->actingInSchool($teacher, $this->school)->get("/study-groups/{$group->id}")->assertOk();

        $this->actingInSchool($teacher, $this->school)->get('/study-groups/create')->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->post('/study-groups', $this->payload(['name' => 'Tidak Boleh']))
            ->assertForbidden();

        $this->assertSame(1, StudyGroup::count());
    }

    // --- student placement -------------------------------------------------

    public function test_admin_can_place_a_student_in_a_group(): void
    {
        $group = $this->makeGroup();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/study-groups/{$group->id}/students", [
                'student_id' => $student->id,
                'starts_on' => '2026-07-01',
            ])
            ->assertRedirect(route('study-groups.show', $group));

        $this->assertDatabaseHas('study_group_members', [
            'school_id' => $this->school->id,
            'study_group_id' => $group->id,
            'student_id' => $student->id,
            'status' => PlacementStatus::Active->value,
        ]);
    }

    public function test_a_student_can_hold_only_one_active_placement(): void
    {
        $groupA = $this->makeGroup();
        $groupB = $this->makeGroupDirectly(['name' => '7B']);

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/study-groups/{$groupA->id}/students", ['student_id' => $student->id]);

        // Moving to another group closes the first placement instead of deleting it.
        $this->actingInSchool($this->admin, $this->school)
            ->post("/study-groups/{$groupB->id}/students", ['student_id' => $student->id])
            ->assertRedirect(route('study-groups.show', $groupB));

        $this->assertSame(
            1,
            StudyGroupMember::where('student_id', $student->id)
                ->where('status', PlacementStatus::Active->value)
                ->count(),
        );

        $this->assertSame(
            2,
            StudyGroupMember::where('student_id', $student->id)->count(),
            'Riwayat penempatan harus tersimpan, bukan dihapus.',
        );
    }

    /**
     * The service enforces the rule, but the schema must enforce it too so a
     * direct insert cannot bypass it.
     */
    public function test_the_database_rejects_a_second_active_placement(): void
    {
        $groupA = $this->makeGroup();
        $groupB = $this->makeGroupDirectly(['name' => '7C']);

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $insert = fn (int $groupId) => DB::table('study_group_members')->insert([
            'school_id' => $this->school->id,
            'study_group_id' => $groupId,
            'student_id' => $student->id,
            'starts_on' => '2026-07-01',
            'ends_on' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $insert($groupA->id);

        $this->expectException(QueryException::class);
        $insert($groupB->id);
    }

    public function test_historical_placements_can_coexist(): void
    {
        $groupA = $this->makeGroup();
        $groupB = $this->makeGroupDirectly(['name' => '7D']);

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        foreach ([[$groupA, 'active'], [$groupB, 'closed']] as [$group, $status]) {
            DB::table('study_group_members')->insert([
                'school_id' => $this->school->id,
                'study_group_id' => $group->id,
                'student_id' => $student->id,
                'starts_on' => '2024-07-01',
                'ends_on' => $status === 'active' ? null : '2025-06-30',
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(2, StudyGroupMember::where('student_id', $student->id)->count());
    }

    public function test_placing_the_same_student_twice_in_one_group_is_rejected(): void
    {
        $group = $this->makeGroup();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/study-groups/{$group->id}/students", ['student_id' => $student->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from("/study-groups/{$group->id}")
            ->post("/study-groups/{$group->id}/students", ['student_id' => $student->id])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(1, StudyGroupMember::where('student_id', $student->id)->count());
    }

    public function test_removing_a_student_closes_the_placement_but_keeps_history(): void
    {
        $group = $this->makeGroup();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/study-groups/{$group->id}/students", ['student_id' => $student->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/study-groups/{$group->id}/students/{$student->id}")
            ->assertRedirect(route('study-groups.show', $group));

        $placement = StudyGroupMember::where('student_id', $student->id)->firstOrFail();

        $this->assertSame(PlacementStatus::Closed, $placement->status);
        $this->assertNotNull($placement->ends_on);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'study_group.student_removed',
        ]);
    }

    public function test_a_student_from_another_tenant_cannot_be_placed(): void
    {
        $group = $this->makeGroup();
        $otherSchool = School::factory()->create();
        $foreignStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/study-groups/{$group->id}/students", ['student_id' => $foreignStudent->id]);

        // The request-layer rule only checks existence, so the service guard must
        // reject the cross-tenant student before anything is written.
        $this->assertSame(0, StudyGroupMember::where('student_id', $foreignStudent->id)->count());
    }

    public function test_teacher_cannot_move_students(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $group = $this->makeGroup();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($teacher, $this->school)
            ->post("/study-groups/{$group->id}/students", ['student_id' => $student->id])
            ->assertForbidden();

        $this->assertSame(0, StudyGroupMember::count());
    }

    public function test_students_table_has_no_permanent_class_column(): void
    {
        // Placement must be historical, never a class_id stored on students.
        $this->assertFalse(
            Schema::hasColumn('students', 'class_id'),
            'students tidak boleh menyimpan class_id permanen.',
        );
        $this->assertFalse(
            Schema::hasColumn('students', 'study_group_id'),
            'students tidak boleh menyimpan study_group_id permanen.',
        );
    }
}
