<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class AttendanceSessionTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();
    }

    public function test_opening_a_session_creates_one_record_per_roster_student(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];
        $students = Student::factory()->count(3)->create(['school_id' => $this->school->id]);

        $this->placeStudents($group, $students->all());

        $this->actingInSchool($this->admin, $this->school)
            ->post('/attendance/sessions', [
                'study_group_id' => $group->id,
                'session_date' => '2026-08-03',
            ])
            ->assertRedirect();

        $session = AttendanceSession::firstOrFail();

        $this->assertSame($this->school->id, $session->school_id);
        $this->assertSame(3, $session->records()->count());
        $this->assertSame(
            $session->records()->count(),
            AttendanceRecord::where('status', AttendanceStatus::Present->value)->count(),
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance_session.opened']);
    }

    public function test_a_session_cannot_be_opened_twice_for_the_same_group_and_day(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $group->id,
            'session_date' => '2026-08-03',
        ])->assertRedirect();

        $this->actingInSchool($this->admin, $this->school)
            ->from('/attendance/sessions')
            ->post('/attendance/sessions', [
                'study_group_id' => $group->id,
                'session_date' => '2026-08-03',
            ])
            ->assertSessionHasErrors('session_date');

        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_the_database_rejects_a_duplicate_session(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];

        $insert = fn () => DB::table('attendance_sessions')->insert([
            'school_id' => $this->school->id,
            'study_group_id' => $group->id,
            'session_date' => '2026-08-03',
            'session_type' => 'daily',
            'status' => 'open',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $insert();

        $this->expectException(QueryException::class);
        $insert();
    }

    public function test_the_same_group_can_have_a_session_on_another_day(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];

        foreach (['2026-08-03', '2026-08-04'] as $date) {
            $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
                'study_group_id' => $group->id,
                'session_date' => $date,
            ])->assertRedirect();
        }

        $this->assertSame(2, AttendanceSession::count());
    }

    public function test_roster_is_resolved_as_of_the_session_date(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];

        // Member since the previous year, still enrolled on the session date.
        $stayed = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($group, [$stayed], '2025-07-01');

        // Left the group before the session date.
        $leftBefore = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($group, [$leftBefore], '2025-07-01', '2026-06-30');

        // Joins the group after the session date.
        $joinsLater = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($group, [$joinsLater], '2026-09-01');

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $group->id,
            'session_date' => '2026-08-03',
        ])->assertRedirect();

        $studentIds = AttendanceRecord::pluck('student_id')->all();

        $this->assertContains($stayed->id, $studentIds, 'Siswa aktif harus diabsen.');
        $this->assertNotContains($leftBefore->id, $studentIds, 'Siswa yang sudah keluar tidak boleh diabsen.');
        $this->assertNotContains($joinsLater->id, $studentIds, 'Siswa yang belum masuk tidak boleh diabsen.');
    }

    public function test_a_student_cannot_have_two_records_in_one_session(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($group, [$student]);

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $group->id,
            'session_date' => '2026-08-03',
        ]);

        $session = AttendanceSession::firstOrFail();

        // Opening the session already created this student's record, so any
        // further insert must be refused by the schema itself.
        $this->expectException(QueryException::class);

        DB::table('attendance_records')->insert([
            'school_id' => $this->school->id,
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'absent',
            'marked_at' => now(),
            'correction_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_archived_study_group_cannot_have_a_session(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];
        $group->update(['status' => 'archived']);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/attendance/sessions')
            ->post('/attendance/sessions', [
                'study_group_id' => $group->id,
                'session_date' => '2026-08-03',
            ])
            ->assertSessionHasErrors('study_group_id');

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_session_date_cannot_be_in_the_future(): void
    {
        $group = $this->makeAcademicContext($this->school)['studyGroup'];

        $this->actingInSchool($this->admin, $this->school)
            ->from('/attendance/sessions')
            ->post('/attendance/sessions', [
                'study_group_id' => $group->id,
                'session_date' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('session_date');
    }

    public function test_session_from_another_tenant_is_not_readable(): void
    {
        $otherSchool = School::factory()->create();
        $otherContext = $this->makeAcademicContext($otherSchool);
        $foreignSession = AttendanceSession::factory()
            ->forGroup($otherContext['studyGroup'], $this->admin, '2026-08-03')
            ->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/attendance/sessions/{$foreignSession->id}")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$foreignSession->id}/finalize")
            ->assertNotFound();

        $this->assertFalse($foreignSession->fresh()->isLocked());
    }

    public function test_teacher_can_read_sessions_but_not_open_them(): void
    {
        $context = $this->makeAssignedTeacherContext($this->school);
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($context['studyGroup'], [$student]);

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $context['studyGroup']->id,
            'session_date' => '2026-08-03',
        ]);

        $session = AttendanceSession::firstOrFail();

        $this->actingInSchool($context['user'], $this->school)
            ->get('/attendance/sessions')
            ->assertOk();

        $this->actingInSchool($context['user'], $this->school)
            ->get("/attendance/sessions/{$session->id}")
            ->assertOk();

        $this->actingInSchool($context['user'], $this->school)
            ->post('/attendance/sessions', [
                'study_group_id' => $context['studyGroup']->id,
                'session_date' => '2026-08-04',
            ])
            ->assertForbidden();

        $this->actingInSchool($context['user'], $this->school)
            ->post("/attendance/sessions/{$session->id}/finalize")
            ->assertForbidden();

        $this->assertFalse($session->fresh()->isLocked());
    }

    public function test_a_teacher_outside_the_assignment_cannot_touch_the_session(): void
    {
        $context = $this->makeAssignedTeacherContext($this->school);
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($context['studyGroup'], [$student]);

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $context['studyGroup']->id,
            'session_date' => '2026-08-03',
        ]);

        $session = AttendanceSession::firstOrFail();
        $record = $session->records()->firstOrFail();

        // A second teacher in the same school who does not teach this group.
        $otherTeacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($otherTeacher, $this->school)
            ->post("/attendance/sessions/{$session->id}/mark", [
                'statuses' => [$record->id => AttendanceStatus::Absent->value],
            ])
            ->assertForbidden();

        $this->assertSame(AttendanceStatus::Present, $record->fresh()->status);
    }
}
