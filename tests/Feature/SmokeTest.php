<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test untuk verifikasi post-deploy.
 *
 * Uji rute kritis agar deploy gagal terdeteksi sebelum user terpengaruh.
 * Tidak mengubah data produksi.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_redirects_when_not_authenticated(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_health_check_returns_ok(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'checked_at', 'checks']);
    }

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_school_selection_page_requires_auth(): void
    {
        $response = $this->get('/schools');

        $response->assertStatus(302);
    }

    public function test_dashboard_requires_selected_tenant(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/schools');
    }

    public function test_can_switch_tenant_with_active_membership(): void
    {
        $user = User::factory()->create();
        $school = School::factory()->create();
        SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/schools/{$school->id}/switch");

        $response->assertRedirect('/dashboard');
    }
}
