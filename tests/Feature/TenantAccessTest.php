<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_active_membership_can_switch_tenant(): void
    {
        $user = User::factory()->create();
        $school = School::factory()->create();
        SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/schools/{$school->id}/switch");

        $response->assertRedirect('/dashboard');
        $this->assertSame($school->id, session('current_school_id'));

        $this->get('/dashboard')->assertOk()->assertSee($school->name);
    }

    public function test_user_without_membership_cannot_switch_tenant(): void
    {
        $user = User::factory()->create();
        $school = School::factory()->create();

        $response = $this->actingAs($user)->post("/schools/{$school->id}/switch");

        $response->assertForbidden();
        $this->assertNull(session('current_school_id'));
    }

    public function test_user_with_inactive_membership_cannot_switch_tenant(): void
    {
        $user = User::factory()->create();
        $school = School::factory()->create();
        SchoolUserMembership::factory()->inactive()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/schools/{$school->id}/switch");

        $response->assertForbidden();
    }

    public function test_school_select_only_lists_accessible_schools(): void
    {
        $user = User::factory()->create();
        $accessible = School::factory()->create(['name' => 'SMP Harapan']);
        $foreign = School::factory()->create(['name' => 'SMP Asing']);
        SchoolUserMembership::factory()->create([
            'school_id' => $accessible->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/schools');

        $response->assertOk();
        $response->assertSee($accessible->name);
        $response->assertDontSee($foreign->name);
    }

    public function test_user_cannot_read_another_tenants_dashboard(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $schoolA = School::factory()->create(['name' => 'Sekolah A']);
        $schoolB = School::factory()->create(['name' => 'Sekolah B']);

        SchoolUserMembership::factory()->create([
            'school_id' => $schoolA->id,
            'user_id' => $userA->id,
        ]);
        SchoolUserMembership::factory()->create([
            'school_id' => $schoolB->id,
            'user_id' => $userB->id,
        ]);

        // User A aktif di sekolah A.
        $this->actingAs($userA)->post("/schools/{$schoolA->id}/switch")->assertRedirect('/dashboard');

        // Session user A dipaksa menyimpan school B: middleware harus menolak.
        $this->withSession(['current_school_id' => $schoolB->id])
            ->actingAs($userA)
            ->get('/dashboard')
            ->assertRedirect('/schools');
    }

    public function test_dashboard_requires_selected_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/schools');
    }

    public function test_revoked_membership_blocks_existing_session(): void
    {
        $user = User::factory()->create();
        $school = School::factory()->create();
        SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->post("/schools/{$school->id}/switch")->assertRedirect('/dashboard');

        $school->memberships()->where('user_id', $user->id)->update(['status' => 'inactive']);

        $this->withSession(['current_school_id' => $school->id])
            ->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/schools');
    }
}
