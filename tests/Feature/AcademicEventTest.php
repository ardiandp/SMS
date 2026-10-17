<?php

namespace Tests\Feature;

use App\Models\AcademicEvent;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * PRD-05: kalender akademik — CRUD dengan tenant guard, validasi tanggal,
 * otorisasi, dan isolasi tenant.
 */
class AcademicEventTest extends TestCase
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

    public function test_admin_creates_event_with_audit(): void
    {
        $year = AcademicYear::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/academic-events', [
                'title' => 'Ujian Tengah Semester',
                'description' => 'UTS ganjil.',
                'starts_at' => '2026-10-20 07:00',
                'ends_at' => '2026-10-24 12:00',
                'category' => 'academic',
                'academic_year_id' => $year->id,
            ])
            ->assertRedirect(route('academic-events.index'));

        $event = AcademicEvent::firstOrFail();

        $this->assertSame($this->school->id, $event->school_id);
        $this->assertSame('Ujian Tengah Semester', $event->title);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academic_event.created']);
    }

    public function test_ends_before_start_is_rejected(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from('/academic-events')
            ->post('/academic-events', [
                'title' => 'Kegiatan Aneh',
                'starts_at' => '2026-10-20 07:00',
                'ends_at' => '2026-10-19 07:00',
            ])
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, AcademicEvent::count());
    }

    public function test_academic_year_from_another_tenant_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $foreignYear = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/academic-events')
            ->post('/academic-events', [
                'title' => 'Kegiatan Aneh',
                'starts_at' => '2026-10-20 07:00',
                'academic_year_id' => $foreignYear->id,
            ])
            ->assertSessionHasErrors('academic_year_id');

        $this->assertSame(0, AcademicEvent::count());
    }

    public function test_admin_deletes_event_with_audit(): void
    {
        $event = AcademicEvent::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/academic-events/{$event->id}")
            ->assertRedirect(route('academic-events.index'));

        $this->assertSame(0, AcademicEvent::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'academic_event.deleted']);
    }

    public function test_teacher_can_view_but_cannot_manage_events(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/academic-events')
            ->assertOk();

        $this->actingInSchool($teacher, $this->school)
            ->post('/academic-events', [
                'title' => 'Coba',
                'starts_at' => '2026-10-20 07:00',
            ])
            ->assertForbidden();

        $this->assertSame(0, AcademicEvent::count());
    }

    public function test_event_from_another_tenant_is_invisible(): void
    {
        $otherSchool = School::factory()->create();
        $foreign = AcademicEvent::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/academic-events/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_dashboard_shows_upcoming_events(): void
    {
        AcademicEvent::factory()->create([
            'school_id' => $this->school->id,
            'title' => 'Pentas Seni Akbar',
            'starts_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(5)->addHours(3)->format('Y-m-d H:i:s'),
        ]);

        AcademicEvent::factory()->create([
            'school_id' => $this->school->id,
            'title' => 'Kegiatan Lama Sekali',
            'starts_at' => now()->subMonths(3)->format('Y-m-d H:i:s'),
            'ends_at' => now()->subMonths(3)->addHours(2)->format('Y-m-d H:i:s'),
        ]);

        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Pentas Seni Akbar')
            ->assertDontSee('Kegiatan Lama Sekali');
    }
}
