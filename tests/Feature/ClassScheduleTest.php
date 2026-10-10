<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\School;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Schedule conflict rules.
 *
 * Overlap detection needs a range comparison that no MySQL/MariaDB index can
 * express, so ScheduleService enforces it. These tests are the only guarantee
 * that the rule actually holds.
 */
class ClassScheduleTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private StudyGroup $studyGroup;

    private TeachingAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $context = $this->makeTeachingContext($this->school);

        $this->studyGroup = $context['studyGroup'];

        $subject = Subject::factory()->create([
            'school_id' => $this->school->id,
            'code' => 'MTK',
        ]);

        $this->assignment = TeachingAssignment::factory()
            ->forGroup($this->studyGroup, $subject, $context['teacher'])
            ->create(['school_id' => $this->school->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'teaching_assignment_id' => $this->assignment->id,
            'day_of_week' => 1,
            'starts_at' => '07:00',
            'ends_at' => '08:30',
        ];
    }

    private function url(): string
    {
        return route('study-groups.schedules.store', $this->studyGroup);
    }

    public function test_admin_can_create_a_schedule(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post($this->url(), $this->payload(['room_label' => 'R-101']))
            ->assertRedirect(route('study-groups.schedules.index', $this->studyGroup));

        $schedule = ClassSchedule::firstOrFail();

        $this->assertSame($this->school->id, $schedule->school_id);
        $this->assertSame(1, $schedule->day_of_week);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class_schedule.created']);
    }

    public function test_the_same_teacher_cannot_be_booked_twice_at_the_same_time(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post($this->url(), $this->payload())
            ->assertRedirect();

        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.schedules.index', $this->studyGroup))
            ->post($this->url(), $this->payload([
                'day_of_week' => 1,
                'starts_at' => '08:00',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, ClassSchedule::count());
    }

    public function test_a_teacher_may_have_two_slots_on_different_days(): void
    {
        $this->actingInSchool($this->admin, $this->school)->post($this->url(), $this->payload());

        $this->actingInSchool($this->admin, $this->school)
            ->post($this->url(), $this->payload([
                'day_of_week' => 2,
                'starts_at' => '07:00',
                'ends_at' => '08:30',
            ]))
            ->assertRedirect(route('study-groups.schedules.index', $this->studyGroup));

        $this->assertSame(2, ClassSchedule::count());
    }

    /**
     * Half-open interval: a lesson ending at 08:30 may be followed by one that
     * starts at 08:30.
     */
    public function test_back_to_back_slots_are_allowed(): void
    {
        $this->actingInSchool($this->admin, $this->school)->post($this->url(), $this->payload());

        $this->actingInSchool($this->admin, $this->school)
            ->post($this->url(), $this->payload([
                'starts_at' => '08:30',
                'ends_at' => '10:00',
            ]))
            ->assertRedirect(route('study-groups.schedules.index', $this->studyGroup));

        $this->assertSame(2, ClassSchedule::count());
    }

    public function test_two_different_teachers_may_use_the_same_room_at_the_same_time(): void
    {
        $otherTeacher = Employee::factory()->create(['school_id' => $this->school->id]);
        $otherSubject = Subject::factory()->create(['school_id' => $this->school->id]);

        $otherAssignment = TeachingAssignment::factory()
            ->forGroup($this->studyGroup, $otherSubject, $otherTeacher)
            ->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)->post($this->url(), $this->payload());

        $this->actingInSchool($this->admin, $this->school)
            ->post($this->url(), $this->payload([
                'teaching_assignment_id' => $otherAssignment->id,
                'room_label' => 'R-101',
            ]))
            ->assertRedirect(route('study-groups.schedules.index', $this->studyGroup));

        $this->assertSame(2, ClassSchedule::count());
    }

    public function test_two_teachers_cannot_share_a_room_at_the_same_time(): void
    {
        $otherTeacher = Employee::factory()->create(['school_id' => $this->school->id]);
        $otherSubject = Subject::factory()->create(['school_id' => $this->school->id]);

        $otherAssignment = TeachingAssignment::factory()
            ->forGroup($this->studyGroup, $otherSubject, $otherTeacher)
            ->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post($this->url(), $this->payload(['room_label' => 'R-101']));

        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.schedules.index', $this->studyGroup))
            ->post($this->url(), $this->payload([
                'teaching_assignment_id' => $otherAssignment->id,
                'starts_at' => '08:00',
                'ends_at' => '09:30',
                'room_label' => 'R-101',
            ]))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, ClassSchedule::count());
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.schedules.index', $this->studyGroup))
            ->post($this->url(), $this->payload([
                'starts_at' => '09:00',
                'ends_at' => '08:30',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_day_of_week_must_be_between_one_and_seven(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.schedules.index', $this->studyGroup))
            ->post($this->url(), $this->payload(['day_of_week' => 9]))
            ->assertSessionHasErrors('day_of_week');
    }

    public function test_assignment_from_another_tenant_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $otherContext = $this->makeTeachingContext($otherSchool);
        $otherSubject = Subject::factory()->create(['school_id' => $otherSchool->id]);

        $foreign = TeachingAssignment::factory()
            ->forGroup($otherContext['studyGroup'], $otherSubject, $otherContext['teacher'])
            ->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from(route('study-groups.schedules.index', $this->studyGroup))
            ->post($this->url(), $this->payload(['teaching_assignment_id' => $foreign->id]))
            ->assertSessionHasErrors('teaching_assignment_id');

        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_assignment_must_belong_to_the_scheduled_study_group(): void
    {
        $otherGroup = StudyGroup::factory()
            ->forContext($this->studyGroup->academicYear, $this->studyGroup->gradeLevel, '7B')
            ->create(['school_id' => $this->school->id]);

        $otherSubject = Subject::factory()->create(['school_id' => $this->school->id]);
        $otherAssignment = TeachingAssignment::factory()
            ->forGroup($otherGroup, $otherSubject, $this->assignment->employee)
            ->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post($this->url(), $this->payload(['teaching_assignment_id' => $otherAssignment->id]));

        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_schedule_from_another_tenant_cannot_be_deleted(): void
    {
        $otherSchool = School::factory()->create();
        $otherContext = $this->makeTeachingContext($otherSchool);
        $otherSubject = Subject::factory()->create(['school_id' => $otherSchool->id]);

        $foreignAssignment = TeachingAssignment::factory()
            ->forGroup($otherContext['studyGroup'], $otherSubject, $otherContext['teacher'])
            ->create(['school_id' => $otherSchool->id]);

        $foreignSchedule = ClassSchedule::factory()->forAssignment($foreignAssignment)->create();

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/schedules/{$foreignSchedule->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('class_schedules', ['id' => $foreignSchedule->id]);
    }

    public function test_teacher_can_read_schedules_but_not_create_them(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get(route('study-groups.schedules.index', $this->studyGroup))
            ->assertOk();

        $this->actingInSchool($teacher, $this->school)
            ->post($this->url(), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, ClassSchedule::count());
    }

    public function test_admin_can_delete_a_schedule(): void
    {
        $this->actingInSchool($this->admin, $this->school)->post($this->url(), $this->payload());

        $schedule = ClassSchedule::firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/schedules/{$schedule->id}");

        $this->assertDatabaseMissing('class_schedules', ['id' => $schedule->id]);
    }

    public function test_day_labels_use_iso_numbering(): void
    {
        $this->assertSame('Senin', ClassSchedule::DAYS[1]);
        $this->assertSame('Minggu', ClassSchedule::DAYS[7]);
    }
}
