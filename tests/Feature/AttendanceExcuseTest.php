<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ExcuseStatus;
use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class AttendanceExcuseTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private AttendanceSession $session;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $group = $this->makeAcademicContext($this->school)['studyGroup'];
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($group, [$student]);

        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $group->id,
            'session_date' => '2026-08-03',
        ]);

        $this->session = AttendanceSession::firstOrFail();
        $this->record = $this->session->records()->firstOrFail();

        $this->markSick();
    }

    private function markSick(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$this->session->id}/mark", [
                'statuses' => [$this->record->id => AttendanceStatus::Sick->value],
            ])
            ->assertRedirect();
    }

    public function test_a_reason_can_be_submitted_for_a_sick_record(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/records/{$this->record->id}/excuse", [
                'reason' => 'Demam, ada surat dokter',
            ])
            ->assertRedirect();

        $excuse = AttendanceExcuse::firstOrFail();

        $this->assertSame($this->school->id, $excuse->school_id);
        $this->assertSame(ExcuseStatus::Pending, $excuse->status);
        $this->assertNull($excuse->document_path);

        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance_excuse.submitted']);
    }

    public function test_an_evidence_file_is_stored_on_the_private_disk(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/records/{$this->record->id}/excuse", [
                'reason' => 'Demam dengan surat dokter',
                'document' => UploadedFile::fake()->create('surat.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $excuse = AttendanceExcuse::firstOrFail();

        $this->assertNotNull($excuse->document_path);
        $this->assertStringStartsWith('attendance-excuses/', $excuse->document_path);
        Storage::disk('local')->assertExists($excuse->document_path);

        // Never reachable through a public URL.
        $this->assertFileDoesNotExist(public_path('storage/'.$excuse->document_path));
    }

    public function test_an_excuse_cannot_be_submitted_for_a_present_record(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$this->session->id}/mark", [
                'statuses' => [$this->record->id => AttendanceStatus::Present->value],
            ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/records/{$this->record->id}/excuse", [
                'reason' => 'Alasan tidak relevan',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, AttendanceExcuse::count());
    }

    public function test_only_one_excuse_per_record(): void
    {
        foreach (['Pertama', 'Kedua'] as $reason) {
            $this->actingInSchool($this->admin, $this->school)
                ->post("/attendance/records/{$this->record->id}/excuse", ['reason' => $reason]);
        }

        $this->assertSame(1, AttendanceExcuse::count());
    }

    public function test_the_database_rejects_a_second_excuse_per_record(): void
    {
        AttendanceExcuse::factory()->forRecord($this->record)->create();

        $this->expectException(QueryException::class);

        AttendanceExcuse::factory()->forRecord($this->record)->create();
    }

    public function test_the_reason_is_required(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from("/attendance/sessions/{$this->session->id}")
            ->post("/attendance/records/{$this->record->id}/excuse", [])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, AttendanceExcuse::count());
    }

    public function test_a_disallowed_document_type_is_rejected(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from("/attendance/sessions/{$this->session->id}")
            ->post("/attendance/records/{$this->record->id}/excuse", [
                'reason' => 'Bukti',
                'document' => UploadedFile::fake()->create('bukti.exe', 10, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('document');

        $this->assertSame(0, AttendanceExcuse::count());
    }

    public function test_an_excuse_can_be_reviewed(): void
    {
        $excuse = AttendanceExcuse::factory()->forRecord($this->record)->create();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/excuses/{$excuse->id}/review", [
                'status' => ExcuseStatus::Approved->value,
                'note' => 'Surat valid',
            ])
            ->assertRedirect();

        $fresh = $excuse->fresh();

        $this->assertSame(ExcuseStatus::Approved, $fresh->status);
        $this->assertSame($this->admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance_excuse.reviewed',
            'actor_user_id' => $this->admin->id,
        ]);
    }

    public function test_review_must_resolve_to_approved_or_rejected(): void
    {
        $excuse = AttendanceExcuse::factory()->forRecord($this->record)->create();

        $this->actingInSchool($this->admin, $this->school)
            ->from('/attendance/excuses')
            ->post("/attendance/excuses/{$excuse->id}/review", [
                'status' => ExcuseStatus::Pending->value,
            ])
            ->assertSessionHasErrors('status');
    }

    /**
     * The reason text can contain personal data, so it must never reach the
     * audit log (AGENTS.md rule 11).
     */
    public function test_the_audit_log_does_not_store_the_reason_text(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/records/{$this->record->id}/excuse", [
                'reason' => 'Bawa perseverealong Indonesian',
            ]);

        $logs = AuditLog::all();

        foreach ($logs as $log) {
            $this->assertStringNotContainsString(
                'Bawa perseverealong Indonesian',
                json_encode($log->metadata_json),
            );
        }
    }

    public function test_an_admin_can_download_the_evidence(): void
    {
        $excuse = AttendanceExcuse::factory()
            ->forRecord($this->record)
            ->withDocument('attendance-excuses/surat.pdf')
            ->create();

        Storage::disk('local')->put('attendance-excuses/surat.pdf', 'isi bukti');

        $this->actingInSchool($this->admin, $this->school)
            ->get("/attendance/excuses/{$excuse->id}/download")
            ->assertOk();
    }

    public function test_a_teacher_cannot_download_evidence(): void
    {
        $context = $this->makeAssignedTeacherContext($this->school);

        $excuse = AttendanceExcuse::factory()
            ->forRecord($this->record)
            ->withDocument('attendance-excuses/surat.pdf')
            ->create();

        Storage::disk('local')->put('attendance-excuses/surat.pdf', 'isi bukti');

        $this->actingInSchool($context['user'], $this->school)
            ->get("/attendance/excuses/{$excuse->id}/download")
            ->assertForbidden();
    }

    public function test_an_excuse_from_another_tenant_is_not_reachable(): void
    {
        $otherSchool = School::factory()->create();
        $otherRecord = AttendanceRecord::factory()
            ->forSession(
                AttendanceSession::factory()->create(['school_id' => $otherSchool->id]),
                $this->admin,
            )
            ->create(['school_id' => $otherSchool->id]);

        $excuse = AttendanceExcuse::factory()->forRecord($otherRecord)->create();

        $this->actingInSchool($this->admin, $this->school)
            ->get("/attendance/excuses/{$excuse->id}/download")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/excuses/{$excuse->id}/review", [
                'status' => ExcuseStatus::Approved->value,
            ])
            ->assertNotFound();

        $this->assertSame(ExcuseStatus::Pending, $excuse->fresh()->status);
    }
}
