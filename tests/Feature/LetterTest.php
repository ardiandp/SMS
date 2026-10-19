<?php

namespace Tests\Feature;

use App\Enums\SchoolLetterStatus;
use App\Models\LetterTemplate;
use App\Models\School;
use App\Models\SchoolLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * PRD-05: template surat & surat sekolah — CRUD, terbit/batalkan,
 * otorisasi, dan isolasi tenant.
 */
class LetterTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();
    }

    public function test_admin_creates_letter_template(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/letter-templates', [
                'code' => 'SKL-001',
                'name' => 'Surat Keterangan Lulus',
                'body_template' => 'Halo {{nama}}, selamat lulus.',
            ])
            ->assertRedirect(route('letter_templates.index'));

        $template = LetterTemplate::firstOrFail();

        $this->assertSame('SKL-001', $template->code);
        $this->assertSame(1, $template->version);
        $this->assertTrue($template->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'letter_template.created']);
    }

    public function test_admin_revises_letter_template(): void
    {
        $template = LetterTemplate::factory()->create([
            'school_id' => $this->school->id,
            'code' => 'SKL-001',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/letter-templates/{$template->id}", [
                'name' => 'Surat Keterangan Lulus (Revisi)',
                'body_template' => 'Halo {{nama}}, selamat lulus dengan pujian.',
            ])
            ->assertRedirect(route('letter_templates.index'));

        $this->assertDatabaseHas('letter_templates', [
            'code' => 'SKL-001',
            'version' => 2,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('letter_templates', [
            'id' => $template->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'letter_template.revised']);
    }

    public function test_admin_deletes_letter_template(): void
    {
        $template = LetterTemplate::factory()->create([
            'school_id' => $this->school->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/letter-templates/{$template->id}")
            ->assertRedirect(route('letter_templates.index'));

        $this->assertDatabaseMissing('letter_templates', ['id' => $template->id]);
    }

    public function test_teacher_cannot_manage_letter_templates(): void
    {
        $this->actingInSchool($this->teacher, $this->school)
            ->post('/letter-templates', [
                'code' => 'SKL-001',
                'name' => 'Template',
                'body_template' => 'Isi template.',
            ])
            ->assertForbidden();

        $this->assertSame(0, LetterTemplate::count());
    }

    public function test_admin_creates_school_letter_draft(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/school-letters', [
                'letter_number' => '001/SM/2026',
                'subject' => 'Undangan Rapat',
                'body' => 'Hadirkan semua guru.',
            ])
            ->assertRedirect(route('school_letters.index'));

        $letter = SchoolLetter::firstOrFail();

        $this->assertSame('001/SM/2026', $letter->letter_number);
        $this->assertSame(SchoolLetterStatus::Draft, $letter->status);
        $this->assertNull($letter->issued_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school_letter.created']);
    }

    public function test_admin_updates_school_letter_draft(): void
    {
        $letter = SchoolLetter::factory()->create([
            'school_id' => $this->school->id,
            'status' => SchoolLetterStatus::Draft,
            'letter_number' => '001/SM/2026',
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/school-letters/{$letter->id}", [
                'letter_number' => '002/SM/2026',
                'subject' => 'Undangan Rapat (Diubah)',
                'body' => 'Hadirkan semua guru dan staf.',
            ])
            ->assertRedirect(route('school_letters.index'));

        $this->assertDatabaseHas('school_letters', [
            'id' => $letter->id,
            'letter_number' => '002/SM/2026',
            'subject' => 'Undangan Rapat (Diubah)',
            'status' => 'draft',
        ]);
    }

    public function test_admin_issues_school_letter(): void
    {
        $letter = SchoolLetter::factory()->create([
            'school_id' => $this->school->id,
            'status' => SchoolLetterStatus::Draft,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/school-letters/{$letter->id}/issue")
            ->assertRedirect(route('school_letters.index'));

        $this->assertSame(SchoolLetterStatus::Issued, $letter->refresh()->status);
        $this->assertNotNull($letter->issued_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school_letter.issued']);
    }

    public function test_admin_cancels_issued_school_letter(): void
    {
        $letter = SchoolLetter::factory()->issued()->create([
            'school_id' => $this->school->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/school-letters/{$letter->id}/cancel")
            ->assertRedirect(route('school_letters.index'));

        $this->assertSame(SchoolLetterStatus::Cancelled, $letter->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school_letter.cancelled']);
    }

    public function test_cannot_issue_non_draft_letter(): void
    {
        $letter = SchoolLetter::factory()->issued()->create([
            'school_id' => $this->school->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/school-letters/{$letter->id}/issue")
            ->assertForbidden();

        $this->assertSame(SchoolLetterStatus::Issued, $letter->refresh()->status);
    }

    public function test_cannot_update_non_draft_letter(): void
    {
        $letter = SchoolLetter::factory()->issued()->create([
            'school_id' => $this->school->id,
            'letter_number' => '001/SM/2026',
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/school-letters/{$letter->id}", [
                'letter_number' => '002/SM/2026',
                'subject' => 'Diubah',
                'body' => 'Isi baru.',
            ])
            ->assertForbidden();

        $this->assertSame('001/SM/2026', $letter->refresh()->letter_number);
    }

    public function test_letter_from_another_tenant_is_invisible(): void
    {
        $otherSchool = School::factory()->create();
        $foreign = SchoolLetter::factory()->create([
            'school_id' => $otherSchool->id,
            'letter_number' => '001/SM/2026',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/school-letters/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_school_letters_index_displays_letters(): void
    {
        SchoolLetter::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->get('/school-letters')
            ->assertOk()
            ->assertSee('Surat Sekolah');
    }

    public function test_letter_templates_index_displays_templates(): void
    {
        LetterTemplate::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->get('/letter-templates')
            ->assertOk()
            ->assertSee('Template & Surat');
    }

    public function test_duplicate_letter_number_is_rejected_within_tenant(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/school-letters', [
                'letter_number' => '001/SM/2026',
                'subject' => 'Undangan Rapat',
                'body' => 'Hadirkan semua guru.',
            ])
            ->assertRedirect(route('school_letters.index'));

        $this->actingInSchool($this->admin, $this->school)
            ->from('/school-letters/create')
            ->post('/school-letters', [
                'letter_number' => '001/SM/2026',
                'subject' => 'Undangan Lain',
                'body' => 'Isi lain.',
            ])
            ->assertSessionHasErrors('letter_number');

        $this->assertSame(1, SchoolLetter::count());

        // Nomor yang sama di tenant lain tetap boleh dipakai (unik per tenant).
        $otherSchool = School::factory()->create();
        $otherAdmin = $this->makeUserWithRole($otherSchool, 'school-admin');
        $this->refreshPermissionCache();

        $this->actingInSchool($otherAdmin, $otherSchool)
            ->post('/school-letters', [
                'letter_number' => '001/SM/2026',
                'subject' => 'Surat Sekolah Lain',
                'body' => 'Isi.',
            ])
            ->assertRedirect(route('school_letters.index'));

        $this->assertSame(1, SchoolLetter::where('school_id', $otherSchool->id)->count());
    }

    public function test_issued_letter_snapshot_survives_template_revision(): void
    {
        $template = LetterTemplate::factory()->create([
            'school_id' => $this->school->id,
            'code' => 'SKL-001',
            'version' => 1,
            'is_active' => true,
            'body_template' => 'Halo {{nama}}, selamat.',
            'created_by' => $this->admin->id,
        ]);

        $letter = SchoolLetter::factory()->create([
            'school_id' => $this->school->id,
            'template_id' => $template->id,
            'letter_number' => '003/SM/2026',
            'status' => SchoolLetterStatus::Draft,
            'body_snapshot' => 'Halo Budi, selamat.',
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/school-letters/{$letter->id}/issue")
            ->assertRedirect(route('school_letters.index'));

        $snapshot = $letter->refresh()->body_snapshot;
        $this->assertSame('Halo Budi, selamat.', $snapshot);

        // Revisi template belakangan tidak boleh mengubah snapshot surat terbit.
        $this->actingInSchool($this->admin, $this->school)
            ->put("/letter-templates/{$template->id}", [
                'name' => 'SKL Revisi',
                'body_template' => 'Halo {{nama}}, selamat DENGAN PUJIAN.',
            ])
            ->assertRedirect(route('letter_templates.index'));

        $this->assertSame($snapshot, $letter->refresh()->body_snapshot);
        $this->assertSame(SchoolLetterStatus::Issued, $letter->refresh()->status);
    }

    public function test_duplicate_template_code_requires_revision(): void
    {
        LetterTemplate::factory()->create([
            'school_id' => $this->school->id,
            'code' => 'SKL-001',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/letter-templates/create')
            ->post('/letter-templates', [
                'code' => 'SKL-001',
                'name' => 'Duplikat',
                'body_template' => 'Isi.',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, LetterTemplate::count());
    }
}