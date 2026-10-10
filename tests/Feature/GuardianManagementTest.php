<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
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

    public function test_admin_can_create_guardian_scoped_to_the_active_tenant(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/guardians', [
                'full_name' => 'Siti Aminah',
                'phone' => '08123456789',
                'email' => 'siti@example.test',
                'address' => 'Jl. Merdeka 10',
            ])
            ->assertRedirect(route('guardians.index'));

        $guardian = Guardian::where('full_name', 'Siti Aminah')->firstOrFail();

        $this->assertSame($this->school->id, $guardian->school_id);
    }

    public function test_guardian_creation_is_audited(): void
    {
        $this->actingInSchool($this->admin, $this->school)->post('/guardians', [
            'full_name' => 'Siti Aminah',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'actor_user_id' => $this->admin->id,
            'action' => 'guardian.created',
            'subject_type' => (new Guardian)->getMorphClass(),
        ]);
    }

    public function test_admin_can_update_guardian_and_it_is_audited(): void
    {
        $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/guardians/{$guardian->id}", [
                'full_name' => 'Siti Aminah Pratama',
                'phone' => '081200000000',
            ])
            ->assertRedirect(route('guardians.index'));

        $this->assertSame('Siti Aminah Pratama', $guardian->fresh()->full_name);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'guardian.updated',
            'subject_id' => $guardian->id,
        ]);
    }

    public function test_guardian_from_another_tenant_is_not_readable_or_updatable(): void
    {
        $otherSchool = School::factory()->create();
        $foreignGuardian = Guardian::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/guardians/{$foreignGuardian->id}/edit")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->put("/guardians/{$foreignGuardian->id}", [
                'full_name' => 'Dicuri',
            ])
            ->assertNotFound();

        $this->assertNotSame('Dicuri', $foreignGuardian->fresh()->full_name);
    }

    public function test_user_without_manage_guardians_permission_is_forbidden(): void
    {
        $staff = $this->makeUserWithRole($this->school, 'staff');
        $this->refreshPermissionCache();

        $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($staff, $this->school)
            ->get('/guardians')
            ->assertForbidden();

        $this->actingInSchool($staff, $this->school)
            ->post('/guardians', ['full_name' => 'Tidak Boleh'])
            ->assertForbidden();

        $this->actingInSchool($staff, $this->school)
            ->put("/guardians/{$guardian->id}", ['full_name' => 'Tidak Boleh'])
            ->assertForbidden();

        $this->assertDatabaseMissing('guardians', ['full_name' => 'Tidak Boleh']);
    }

    public function test_search_is_scoped_to_the_active_tenant(): void
    {
        $mine = Guardian::factory()->create([
            'school_id' => $this->school->id,
            'full_name' => 'Zainuddin Latif',
        ]);

        $otherSchool = School::factory()->create();
        Guardian::factory()->create([
            'school_id' => $otherSchool->id,
            'full_name' => 'Zainuddin Tenant Lain',
        ]);

        $response = $this->actingInSchool($this->admin, $this->school)
            ->get('/guardians?q=Zainuddin')
            ->assertOk();

        $response->assertSee($mine->full_name);
        $response->assertDontSee('Zainuddin Tenant Lain');
    }

    public function test_index_paginates_server_side(): void
    {
        Guardian::factory()->count(20)->create(['school_id' => $this->school->id]);

        $response = $this->actingInSchool($this->admin, $this->school)
            ->get('/guardians')
            ->assertOk();

        $page = $response->viewData('guardians');

        $this->assertSame(15, $page->perPage());
        $this->assertCount(15, $page->items());
        $this->assertSame(20, $page->total());
        $this->assertSame(20, Guardian::where('school_id', $this->school->id)->count());
    }
}
