<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\School;
use App\Models\Semester;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class TeachingAssignmentTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private StudyGroup $studyGroup;

    private Subject $subject;

    private Employee $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $context = $this->makeTeachingContext($this->school);

        $this->studyGroup = $context['studyGroup'];
        $this->teacher = $context['teacher'];

        $this->subject = Subject::factory()->create([
            'school_id' => $this->school->id,
            'code' => 'MTK',
            'name' => 'Matematika',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'study_group_id' => $this->studyGroup->id,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
            'status' => 'active',
        ];
    }

    public function test_admin_can_create_a_teaching_assignment(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/teaching-assignments', $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('teaching_assignments', [
            'school_id' => $this->school->id,
            'study_group_id' => $this->studyGroup->id,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'teaching_assignment.created',
        ]);
    }

    public function test_subject_from_another_tenant_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $foreignSubject = Subject::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.show', $this->studyGroup))
            ->post('/teaching-assignments', $this->payload(['subject_id' => $foreignSubject->id]))
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(0, TeachingAssignment::count());
    }

    public function test_employee_from_another_tenant_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $foreignEmployee = Employee::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.show', $this->studyGroup))
            ->post('/teaching-assignments', $this->payload(['employee_id' => $foreignEmployee->id]))
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(0, TeachingAssignment::count());
    }

    public function test_the_same_teacher_cannot_be_assigned_the_same_subject_twice(): void
    {
        $this->actingInSchool($this->admin, $this->school)->post('/teaching-assignments', $this->payload());

        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.show', $this->studyGroup))
            ->post('/teaching-assignments', $this->payload())
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(1, TeachingAssignment::count());
    }

    public function test_the_same_teacher_may_teach_the_same_subject_in_another_group(): void
    {
        $otherGroup = StudyGroup::factory()
            ->forContext($this->studyGroup->academicYear, $this->studyGroup->gradeLevel, '7B')
            ->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)->post('/teaching-assignments', $this->payload());

        $this->actingInSchool($this->admin, $this->school)
            ->post('/teaching-assignments', $this->payload(['study_group_id' => $otherGroup->id]))
            ->assertRedirect();

        $this->assertSame(2, TeachingAssignment::count());
    }

    public function test_the_same_teacher_may_teach_the_same_subject_in_another_semester(): void
    {
        $year = $this->studyGroup->academicYear;
        $firstSemester = Semester::factory()->forYear($year)->numbered(2)->create([
            'school_id' => $this->school->id,
        ]);
        $secondSemester = Semester::factory()->forYear($year)->numbered(3)->create([
            'school_id' => $this->school->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/teaching-assignments', $this->payload(['semester_id' => $firstSemester->id]));

        $this->actingInSchool($this->admin, $this->school)
            ->post('/teaching-assignments', $this->payload(['semester_id' => $secondSemester->id]))
            ->assertRedirect();

        $this->assertSame(2, TeachingAssignment::count());
    }

    public function test_semester_from_another_academic_year_is_rejected(): void
    {
        $otherYear = AcademicYear::factory()->create(['school_id' => $this->school->id]);
        $foreignSemester = Semester::factory()->forYear($otherYear)->create([
            'school_id' => $this->school->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.show', $this->studyGroup))
            ->post('/teaching-assignments', $this->payload(['semester_id' => $foreignSemester->id]))
            ->assertSessionHasErrors('semester_id');

        $this->assertSame(0, TeachingAssignment::count());
    }

    public function test_assignment_from_another_tenant_is_not_readable_or_mutable(): void
    {
        $otherSchool = School::factory()->create();
        $otherContext = $this->makeTeachingContext($otherSchool);
        $otherSubject = Subject::factory()->create(['school_id' => $otherSchool->id]);

        $foreign = TeachingAssignment::factory()
            ->forGroup($otherContext['studyGroup'], $otherSubject, $otherContext['teacher'])
            ->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get('/teaching-assignments')
            ->assertOk()
            ->assertDontSee($otherSubject->name);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/teaching-assignments/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('teaching_assignments', ['id' => $foreign->id]);
    }

    public function test_teacher_can_read_assignments_but_not_create_them(): void
    {
        $teacherUser = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($this->admin, $this->school)
            ->post('/teaching-assignments', $this->payload());

        $this->actingInSchool($teacherUser, $this->school)
            ->get('/teaching-assignments')
            ->assertOk();

        $this->actingInSchool($teacherUser, $this->school)
            ->post('/teaching-assignments', $this->payload())
            ->assertForbidden();

        $this->assertSame(1, TeachingAssignment::count());
    }

    public function test_an_assignment_with_schedules_cannot_be_deleted(): void
    {
        $assignment = TeachingAssignment::factory()
            ->forGroup($this->studyGroup, $this->subject, $this->teacher)
            ->create(['school_id' => $this->school->id]);

        ClassSchedule::factory()->forAssignment($assignment)->create();

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/teaching-assignments/{$assignment->id}");

        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignment->id]);
    }

    public function test_an_assignment_without_schedules_can_be_deleted(): void
    {
        $assignment = TeachingAssignment::factory()
            ->forGroup($this->studyGroup, $this->subject, $this->teacher)
            ->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/teaching-assignments/{$assignment->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('teaching_assignments', ['id' => $assignment->id]);
    }
}
