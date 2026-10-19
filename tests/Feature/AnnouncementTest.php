<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * PRD-05: pengumuman sekolah — alur draf/terbit/arsip, sanitasi XSS,
 * audience eksplisit, otorisasi, dan isolasi tenant.
 */
class AnnouncementTest extends TestCase
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

    public function test_admin_creates_draft_with_sanitized_body(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/announcements', [
                'title' => 'Libur Semester',
                'body' => '<p>Libur <b>dimulai</b> Senin.</p><script>alert("xss")</script>',
                'audiences' => [['audience_type' => 'all', 'audience_id' => null]],
            ])
            ->assertRedirect(route('announcements.index'));

        $announcement = Announcement::firstOrFail();

        $this->assertSame('draft', $announcement->status->value);
        $this->assertStringNotContainsString('<script>', $announcement->body);
        $this->assertStringContainsString('<b>dimulai</b>', $announcement->body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.created']);
    }

    public function test_publish_requires_explicit_audience(): void
    {
        $announcement = Announcement::factory()->create([
            'school_id' => $this->school->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/announcements')
            ->post("/announcements/{$announcement->id}/publish")
            ->assertSessionHasErrors('audiences');

        $this->assertSame('draft', $announcement->refresh()->status->value);
    }

    public function test_publish_then_archive_flow_with_audit(): void
    {
        $announcement = Announcement::factory()->create([
            'school_id' => $this->school->id,
            'created_by' => $this->admin->id,
        ]);
        $announcement->recipients()->create([
            'school_id' => $this->school->id,
            'audience_type' => 'all',
            'audience_id' => null,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/announcements/{$announcement->id}/publish")
            ->assertRedirect(route('announcements.index'));

        $this->assertSame('published', $announcement->refresh()->status->value);
        $this->assertNotNull($announcement->published_at);

        // Pengumuman terbit tidak bisa diubah diam-diam: service menolak
        // revisi dengan error validasi, bukan update senyap.
        $this->actingInSchool($this->admin, $this->school)
            ->from('/announcements')
            ->put("/announcements/{$announcement->id}", [
                'title' => 'Diubah',
                'body' => '<p>Diubah.</p>',
                'audiences' => [['audience_type' => 'all', 'audience_id' => null]],
            ])
            ->assertSessionHasErrors('status');

        $this->actingInSchool($this->admin, $this->school)
            ->post("/announcements/{$announcement->id}/archive")
            ->assertRedirect(route('announcements.index'));

        $this->assertSame('archived', $announcement->refresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.published']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.archived']);
    }

    public function test_teacher_can_read_but_cannot_manage_announcements(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/announcements')
            ->assertOk();

        $this->actingInSchool($teacher, $this->school)
            ->post('/announcements', [
                'title' => 'Coba',
                'body' => '<p>Coba.</p>',
                'audiences' => [['audience_type' => 'all', 'audience_id' => null]],
            ])
            ->assertForbidden();

        $this->assertSame(0, Announcement::count());
    }

    public function test_announcement_from_another_tenant_is_invisible(): void
    {
        $otherSchool = School::factory()->create();
        $foreign = Announcement::factory()->published()->create(['school_id' => $otherSchool->id]);
        $foreign->recipients()->create([
            'school_id' => $otherSchool->id,
            'audience_type' => 'all',
            'audience_id' => null,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/announcements/{$foreign->id}/publish")
            ->assertNotFound();
    }

    public function test_dashboard_shows_published_announcements(): void
    {
        $announcement = Announcement::factory()->published()->create([
            'school_id' => $this->school->id,
            'title' => 'Upacara Bendera Senin',
            'created_by' => $this->admin->id,
        ]);
        $announcement->recipients()->create([
            'school_id' => $this->school->id,
            'audience_type' => 'all',
            'audience_id' => null,
        ]);

        Announcement::factory()->create([
            'school_id' => $this->school->id,
            'title' => 'Draf Rahasia',
            'created_by' => $this->admin->id,
        ]);

        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Upacara Bendera Senin')
            ->assertDontSee('Draf Rahasia');
    }

    public function test_dashboard_hides_role_scoped_announcement_from_other_roles(): void
    {
        $teacherRoleId = \Spatie\Permission\Models\Role::findByName('teacher', 'web')->getKey();
        $staffRoleId = \Spatie\Permission\Models\Role::findByName('staff', 'web')->getKey();

        $announcement = Announcement::factory()->published()->create([
            'school_id' => $this->school->id,
            'title' => 'Rapat Guru Khusus',
            'created_by' => $this->admin->id,
        ]);
        $announcement->recipients()->create([
            'school_id' => $this->school->id,
            'audience_type' => 'role',
            'audience_id' => $teacherRoleId,
        ]);

        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $staff = $this->makeUserWithRole($this->school, 'staff');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Rapat Guru Khusus');

        $this->actingInSchool($staff, $this->school)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Rapat Guru Khusus');

        $this->assertNotSame($teacherRoleId, $staffRoleId);
    }
}
