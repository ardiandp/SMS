<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private StudyGroup $group;

    /**
     * Open a session with the given statuses applied, keyed by student index.
     *
     * @param  array<int, AttendanceStatus>  $statuses
     * @return array{0: AttendanceSession, 1: Collection<int, Student>}
     */
    private function sessionWith(array $statuses, string $date = '2026-08-03'): array
    {
        $students = Student::factory()->count(count($statuses))
            ->create(['school_id' => $this->school->id]);

        $this->placeStudents($this->group, $students->all());

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $this->group->id,
            'session_date' => $date,
        ])->assertRedirect();

        $session = AttendanceSession::where('study_group_id', $this->group->id)
            ->whereDate('session_date', $date)
            ->firstOrFail();

        $map = [];

        foreach ($statuses as $index => $status) {
            $student = $students[$index];
            $map[$student->id] = $status;
        }

        foreach ($map as $studentId => $status) {
            $record = $session->records()->where('student_id', $studentId)->firstOrFail();

            $this->actingInSchool($this->admin, $this->school)
                ->post("/attendance/sessions/{$session->id}/mark", [
                    'statuses' => [$record->id => $status->value],
                ])
                ->assertRedirect();
        }

        return [$session, $students];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $this->group = $this->makeAcademicContext($this->school)['studyGroup'];
    }

    public function test_marking_changes_only_the_submitted_records(): void
    {
        [$session, $students] = $this->sessionWith([
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Present,
        ]);

        $records = $session->records()->orderBy('id')->get();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/mark", [
                'statuses' => [
                    $records[0]->id => AttendanceStatus::Sick->value,
                    $records[1]->id => AttendanceStatus::Absent->value,
                ],
                'notes' => 'Keterangan hari ini',
            ])
            ->assertRedirect();

        $fresh = $session->records()->orderBy('id')->get();

        $this->assertSame(AttendanceStatus::Sick, $fresh[0]->status);
        $this->assertSame(AttendanceStatus::Absent, $fresh[1]->status);
        $this->assertSame(AttendanceStatus::Present, $fresh[2]->status);
        $this->assertSame('Keterangan hari ini', $fresh[0]->notes);
        $this->assertSame($this->admin->id, $fresh[0]->marked_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance_records.marked',
            'actor_user_id' => $this->admin->id,
        ]);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Present]);
        $record = $session->records()->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->from("/attendance/sessions/{$session->id}")
            ->post("/attendance/sessions/{$session->id}/mark", [
                'statuses' => [$record->id => 'bolos'],
            ])
            ->assertSessionHasErrors("statuses.{$record->id}");

        $this->assertSame(AttendanceStatus::Present, $record->fresh()->status);
    }

    public function test_a_record_from_another_session_cannot_be_marked_here(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Present], '2026-08-03');

        // A second group with its own session on a different date.
        $otherGroup = StudyGroup::factory()
            ->forContext($this->group->academicYear, $this->group->gradeLevel, '7Z')
            ->create(['school_id' => $this->school->id]);

        $this->group = $otherGroup;
        $this->sessionWith([AttendanceStatus::Present], '2026-08-04');

        // The record that belongs to the *other* session.
        $foreignRecord = AttendanceRecord::where('attendance_session_id', '!=', $session->id)
            ->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->from("/attendance/sessions/{$session->id}")
            ->post("/attendance/sessions/{$session->id}/mark", [
                'statuses' => [$foreignRecord->id => AttendanceStatus::Absent->value],
            ])
            ->assertSessionHasErrors("statuses.{$foreignRecord->id}");

        $this->assertSame(AttendanceStatus::Present, $foreignRecord->fresh()->status);
    }

    public function test_mark_all_updates_every_record(): void
    {
        [$session] = $this->sessionWith([
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Present,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/mark-all", [
                'status' => AttendanceStatus::Absent->value,
            ])
            ->assertRedirect();

        $this->assertSame(
            3,
            AttendanceRecord::where('status', AttendanceStatus::Absent->value)->count(),
        );
    }

    public function test_a_finalized_session_rejects_normal_marking(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Present]);
        $record = $session->records()->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/finalize")
            ->assertRedirect();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/mark", [
                'statuses' => [$record->id => AttendanceStatus::Absent->value],
            ])
            ->assertSessionHasErrors('session');

        $this->assertSame(AttendanceStatus::Present, $record->fresh()->status);
    }

    public function test_a_correction_records_actor_time_and_previous_status(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Absent]);
        $record = $session->records()->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/finalize");

        $this->actingInSchool($this->admin, $this->school)
            ->put("/attendance/records/{$record->id}/correct", [
                'status' => AttendanceStatus::Sick->value,
                'reason' => 'Sakit berdasarkan surat dokter',
            ])
            ->assertRedirect();

        $fresh = $record->fresh();

        $this->assertSame(AttendanceStatus::Sick, $fresh->status);
        $this->assertSame(AttendanceStatus::Absent, $fresh->previous_status);
        $this->assertSame($this->admin->id, $fresh->corrected_by);
        $this->assertNotNull($fresh->corrected_at);
        $this->assertSame(1, $fresh->correction_count);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance_record.corrected',
            'actor_user_id' => $this->admin->id,
            'subject_id' => $record->id,
        ]);
    }

    public function test_correction_requires_a_reason(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Absent]);
        $record = $session->records()->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->from("/attendance/sessions/{$session->id}")
            ->put("/attendance/records/{$record->id}/correct", [
                'status' => AttendanceStatus::Sick->value,
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(AttendanceStatus::Absent, $record->fresh()->status);
    }

    public function test_correction_to_the_same_status_is_rejected(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Absent]);
        $record = $session->records()->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->from("/attendance/sessions/{$session->id}")
            ->put("/attendance/records/{$record->id}/correct", [
                'status' => AttendanceStatus::Absent->value,
                'reason' => 'Tidak ada perubahan',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, $record->fresh()->correction_count);
    }

    public function test_teacher_cannot_correct_without_the_permission(): void
    {
        $context = $this->makeAssignedTeacherContext($this->school);
        $this->group = $context['studyGroup'];

        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($this->group, [$student]);

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $this->group->id,
            'session_date' => '2026-08-03',
        ]);

        $session = AttendanceSession::firstOrFail();
        $record = $session->records()->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/finalize");

        // Guru punya view.attendance, tetapi bukan correct.attendance.
        $this->actingInSchool($context['user'], $this->school)
            ->put("/attendance/records/{$record->id}/correct", [
                'status' => AttendanceStatus::Present->value,
                'reason' => 'Minta perubahan',
            ])
            ->assertForbidden();

        $this->assertSame(AttendanceStatus::Present, $record->fresh()->status);
    }

    public function test_reopening_a_finalized_session_is_audited(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Present]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/finalize");

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/reopen", [
                'reason' => 'Salah input Choices',
            ])
            ->assertRedirect();

        $this->assertFalse($session->fresh()->isLocked());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance_session.reopened',
            'actor_user_id' => $this->admin->id,
        ]);
    }

    public function test_reopening_requires_a_reason(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Present]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/finalize");

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/reopen")
            ->assertSessionHasErrors('reason');

        $this->assertTrue($session->fresh()->isLocked());
    }

    public function test_attaching_a_student_twice_is_rejected(): void
    {
        [$session, $students] = $this->sessionWith([AttendanceStatus::Present]);
        $student = $students->first();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/students", [
                'student_id' => $student->id,
                'status' => AttendanceStatus::Absent->value,
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(1, $session->records()->count());
    }

    public function test_a_student_from_another_tenant_cannot_be_attached(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Present]);

        $otherSchool = School::factory()->create();
        $foreignStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/students", [
                'student_id' => $foreignStudent->id,
                'status' => AttendanceStatus::Present->value,
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('attendance_records', ['student_id' => $foreignStudent->id]);
    }

    public function test_a_record_from_another_tenant_cannot_be_corrected(): void
    {
        [$session] = $this->sessionWith([AttendanceStatus::Absent]);
        $record = $session->records()->firstOrFail();

        // Re-home the record into a real other school to simulate a leaked id.
        $otherSchool = School::factory()->create();
        DB::table('attendance_records')->where('id', $record->id)->update(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/attendance/records/{$record->id}/correct", [
                'status' => AttendanceStatus::Present->value,
                'reason' => 'Percobaan lintas tenant',
            ])
            ->assertNotFound();
    }
}
