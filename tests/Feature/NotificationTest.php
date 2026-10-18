<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\School;
use App\Models\SchoolLetter;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\LetterIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * PRD-05: notifikasi in-app — penerima mengikuti audience/otorisasi,
 * payload tidak membawa isi sensitif (tidak jadi jalur bocor data),
 * tanda sudah dibaca, dan isolasi tenant.
 */
class NotificationTest extends TestCase
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

    public function test_publishing_announcement_notifies_audience_members(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $announcement = Announcement::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'draft',
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

        // Anggota sekolah lain menerima notifikasi; pelaku publish tidak.
        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->school->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $teacher->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $this->admin->id,
        ]);
    }

    public function test_announcement_payload_does_not_leak_body(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');

        $announcement = Announcement::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'draft',
            'title' => 'Libur Semester',
            'body' => 'Isi sensitif libur semester.',
            'created_by' => $this->admin->id,
        ]);
        $announcement->recipients()->create([
            'school_id' => $this->school->id,
            'audience_type' => 'all',
            'audience_id' => null,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/announcements/{$announcement->id}/publish");

        $payload = $teacher->notifications()->firstOrFail()->data;

        $this->assertStringContainsString('Libur Semester', $payload['title']);
        $this->assertStringNotContainsString('sensitif', json_encode($payload));
    }

    public function test_letter_issue_notifies_school_managers_not_actor(): void
    {
        $otherAdmin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $letter = SchoolLetter::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/school-letters/{$letter->id}/issue")
            ->assertRedirect(route('school_letters.index'));

        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->school->id,
            'notifiable_id' => $otherAdmin->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $this->admin->id,
        ]);

        // Isi surat (body_snapshot) tidak ikut dalam payload notifikasi.
        $payload = $otherAdmin->notifications()->firstOrFail()->data;
        $this->assertStringNotContainsString($letter->body_snapshot, json_encode($payload));
    }

    public function test_user_sees_own_notifications_and_marks_read(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $announcement = Announcement::factory()->create(['school_id' => $this->school->id]);
        $announcement->recipients()->create([
            'school_id' => $this->school->id,
            'audience_type' => 'all',
            'audience_id' => null,
        ]);

        // notify() dijalankan dalam konteks sekolah agar school_id terisi
        // (model Notification menolak baris tanpa pemilik sekolah).
        $this->actingInSchool($teacher, $this->school);
        $teacher->notify(new AnnouncementPublished($announcement));

        $notification = $teacher->notifications()->firstOrFail();

        $this->actingInSchool($teacher, $this->school)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Pengumuman baru')
            ->assertSee('1 belum dibaca');

        $this->actingInSchool($teacher, $this->school)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->refresh()->read_at);

        $this->actingInSchool($teacher, $this->school)
            ->get('/notifications')
            ->assertSee('0 belum dibaca');
    }

    public function test_user_cannot_mark_someone_elses_notification(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $announcement = Announcement::factory()->create(['school_id' => $this->school->id]);

        // notify() dijalankan dalam konteks sekolah agar school_id terisi.
        $this->actingInSchool($teacher, $this->school);
        $teacher->notify(new AnnouncementPublished($announcement));

        $notification = $teacher->notifications()->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_notification_from_another_tenant_is_invisible(): void
    {
        $otherSchool = School::factory()->create();
        $otherUser = $this->makeUserWithRole($otherSchool, 'school-admin');
        $announcement = Announcement::factory()->create(['school_id' => $otherSchool->id]);

        // Notifikasi dibuat dalam konteks sekolahnya sendiri; SchoolScope
        // lalu menyembunyikan baris itu dari daftar maupun route binding
        // (mark-read akan 404) saat konteks berpindah ke sekolah lain.
        $this->actingInSchool($otherUser, $otherSchool);
        $otherUser->notify(new LetterIssued(
            SchoolLetter::factory()->create(['school_id' => $otherSchool->id]),
        ));

        $this->actingInSchool($this->admin, $this->school)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Belum ada notifikasi')
            ->assertDontSee('Surat terbit');
    }
}
