<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
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

    public function test_admin_can_create_employee_scoped_to_the_active_tenant(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/employees', [
                'employee_number' => 'G001',
                'full_name' => 'Dewi Lestari',
                'employment_status' => 'permanent',
                'joined_on' => '2020-07-01',
            ])
            ->assertRedirect(route('employees.index'));

        $employee = Employee::where('employee_number', 'G001')->firstOrFail();

        $this->assertSame($this->school->id, $employee->school_id);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'actor_user_id' => $this->admin->id,
            'action' => 'employee.created',
        ]);
    }

    public function test_duplicate_employee_number_in_same_school_is_rejected(): void
    {
        Employee::factory()->create([
            'school_id' => $this->school->id,
            'employee_number' => 'G001',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/employees/create')
            ->post('/employees', [
                'employee_number' => 'G001',
                'full_name' => 'Duplikat',
                'employment_status' => 'contract',
            ])
            ->assertRedirect('/employees/create')
            ->assertSessionHasErrors('employee_number');

        $this->assertSame(1, Employee::where('employee_number', 'G001')->count());
    }

    public function test_same_employee_number_in_different_school_is_allowed(): void
    {
        $otherSchool = School::factory()->create();
        Employee::factory()->create([
            'school_id' => $otherSchool->id,
            'employee_number' => 'SHARED1',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/employees', [
                'employee_number' => 'SHARED1',
                'full_name' => 'Pegawai Sekolah Ini',
                'employment_status' => 'permanent',
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertSame(1, Employee::where('school_id', $this->school->id)
            ->where('employee_number', 'SHARED1')->count());
    }

    public function test_employee_update_is_audited(): void
    {
        $employee = Employee::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/employees/{$employee->id}", [
                'employee_number' => $employee->employee_number,
                'full_name' => 'Nama Diperbarui',
                'employment_status' => 'contract',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $this->assertSame('Nama Diperbarui', $employee->fresh()->full_name);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'employee.updated',
            'subject_id' => $employee->id,
        ]);
    }

    public function test_destroy_deactivates_employee_instead_of_deleting(): void
    {
        $employee = Employee::factory()->create([
            'school_id' => $this->school->id,
            'employment_status' => 'permanent',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/employees/{$employee->id}")
            ->assertRedirect(route('employees.index'));

        $fresh = $employee->fresh();

        $this->assertNotNull($fresh, 'Pegawai tidak boleh dihapus permanen.');
        $this->assertSame(EmploymentStatus::Former, $fresh->employment_status);
        $this->assertNotNull($fresh->left_on);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'employee.deactivated',
            'subject_id' => $employee->id,
        ]);
    }

    public function test_employee_from_another_tenant_is_not_readable_or_mutable(): void
    {
        $otherSchool = School::factory()->create();
        $foreign = Employee::factory()->create([
            'school_id' => $otherSchool->id,
            'full_name' => 'Pegawai Tenant Lain',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/employees/{$foreign->id}")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->put("/employees/{$foreign->id}", [
                'employee_number' => $foreign->employee_number,
                'full_name' => 'Diretas',
                'employment_status' => 'permanent',
            ])
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/employees/{$foreign->id}")
            ->assertNotFound();

        $this->assertSame('Pegawai Tenant Lain', $foreign->fresh()->full_name);
    }

    public function test_user_without_manage_employees_permission_is_forbidden(): void
    {
        $staff = $this->makeUserWithRole($this->school, 'staff');
        $this->refreshPermissionCache();

        $employee = Employee::factory()->create(['school_id' => $this->school->id]);

        // `staff` memegang view.employees, jadi daftar tetap bisa dibaca.
        $this->actingInSchool($staff, $this->school)
            ->get('/employees')
            ->assertOk();

        $this->actingInSchool($staff, $this->school)
            ->get("/employees/{$employee->id}")
            ->assertOk();

        $this->actingInSchool($staff, $this->school)
            ->post('/employees', [
                'employee_number' => 'G999',
                'full_name' => 'Tidak Boleh',
                'employment_status' => 'permanent',
            ])
            ->assertForbidden();

        $this->actingInSchool($staff, $this->school)
            ->delete("/employees/{$employee->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('employees', ['employee_number' => 'G999']);
    }

    public function test_search_is_scoped_to_the_active_tenant(): void
    {
        $mine = Employee::factory()->create([
            'school_id' => $this->school->id,
            'full_name' => 'Rahmawati Unique',
        ]);

        $otherSchool = School::factory()->create();
        Employee::factory()->create([
            'school_id' => $otherSchool->id,
            'full_name' => 'Rahmawati Tenant Lain',
        ]);

        $response = $this->actingInSchool($this->admin, $this->school)
            ->get('/employees?q=Rahmawati')
            ->assertOk();

        $response->assertSee($mine->full_name);
        $response->assertDontSee('Rahmawati Tenant Lain');
    }

    public function test_left_on_must_not_precede_joined_on(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from('/employees/create')
            ->post('/employees', [
                'employee_number' => 'G002',
                'full_name' => 'Tanggal Salah',
                'employment_status' => 'permanent',
                'joined_on' => '2024-01-01',
                'left_on' => '2020-01-01',
            ])
            ->assertSessionHasErrors('left_on');
    }
}
