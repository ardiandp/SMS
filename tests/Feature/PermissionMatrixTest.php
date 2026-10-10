<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Server-side authorization matrix. Every assertion is a real HTTP response:
 * hiding a button in Blade must never be the only guard (PRD-01 / AGENTS.md rule 5).
 */
class PermissionMatrixTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->refreshPermissionCache();
    }

    public function test_teacher_can_read_students_but_cannot_manage_them(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($teacher, $this->school)->get('/students')->assertOk();
        $this->actingInSchool($teacher, $this->school)->get("/students/{$student->id}")->assertOk();

        $this->actingInSchool($teacher, $this->school)->get('/students/create')->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->post('/students', [
                'nis' => 'T001',
                'full_name' => 'Tidak Boleh',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->put("/students/{$student->id}", [
                'nis' => $student->nis,
                'full_name' => 'Tidak Boleh',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->delete("/students/{$student->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('students', ['nis' => 'T001']);
        $this->assertSame($student->full_name, $student->fresh()->full_name);
    }

    public function test_teacher_cannot_reach_student_import(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/students/import')
            ->assertForbidden();
    }

    public function test_staff_can_view_students_and_manage_documents_but_cannot_create_students(): void
    {
        $staff = $this->makeUserWithRole($this->school, 'staff');
        $this->refreshPermissionCache();

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($staff, $this->school)->get('/students')->assertOk();

        $this->actingInSchool($staff, $this->school)->get('/students/create')->assertForbidden();

        $this->actingInSchool($staff, $this->school)
            ->post('/students', [
                'nis' => 'S001',
                'full_name' => 'Tidak Boleh',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingInSchool($staff, $this->school)
            ->delete("/students/{$student->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('students', ['nis' => 'S001']);
    }

    public function test_staff_cannot_manage_guardians_or_employees(): void
    {
        $staff = $this->makeUserWithRole($this->school, 'staff');
        $this->refreshPermissionCache();

        $employee = Employee::factory()->create(['school_id' => $this->school->id]);

        // manage.guardians tidak dipegang staff sama sekali.
        $this->actingInSchool($staff, $this->school)->get('/guardians')->assertForbidden();

        $this->actingInSchool($staff, $this->school)
            ->post('/guardians', ['full_name' => 'Tidak Boleh'])
            ->assertForbidden();

        // staff hanya view.employees: boleh baca, tidak boleh ubah/nonaktifkan.
        $this->actingInSchool($staff, $this->school)->get('/employees')->assertOk();

        $this->actingInSchool($staff, $this->school)
            ->put("/employees/{$employee->id}", [
                'employee_number' => $employee->employee_number,
                'full_name' => 'Tidak Boleh',
                'employment_status' => 'permanent',
            ])
            ->assertForbidden();

        $this->actingInSchool($staff, $this->school)
            ->delete("/employees/{$employee->id}")
            ->assertForbidden();

        $this->assertSame($employee->full_name, $employee->fresh()->full_name);
    }

    public function test_teacher_cannot_read_employee_records(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $employee = Employee::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($teacher, $this->school)
            ->get("/employees/{$employee->id}")
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/students')->assertRedirect('/login');
        $this->get('/guardians')->assertRedirect(route('login'));
        $this->get('/employees')->assertRedirect(route('login'));
        $this->get('/students/import')->assertRedirect(route('login'));
    }

    public function test_user_without_active_tenant_is_redirected_to_school_select(): void
    {
        $admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $this->actingAs($admin)
            ->get('/students')
            ->assertRedirect(route('schools.select'));

        $this->actingAs($admin)
            ->get('/guardians')
            ->assertRedirect(route('schools.select'));
    }

    public function test_user_without_any_membership_cannot_read_tenant_data(): void
    {
        $outsider = User::factory()->create();
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        // Memalsukan school_id di session tidak cukup: middleware menolak
        // karena tidak ada membership aktif.
        $this->withSession(['current_school_id' => $this->school->id])
            ->actingAs($outsider)
            ->get("/students/{$student->id}")
            ->assertRedirect(route('schools.select'));
    }

    public function test_admin_from_another_tenant_cannot_touch_this_tenants_records(): void
    {
        $otherSchool = School::factory()->create();
        $otherAdmin = $this->makeUserWithRole($otherSchool, 'school-admin');
        $this->refreshPermissionCache();

        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($otherAdmin, $otherSchool)
            ->get("/students/{$student->id}")
            ->assertNotFound();

        $this->actingInSchool($otherAdmin, $otherSchool)
            ->put("/students/{$student->id}", [
                'nis' => $student->nis,
                'full_name' => 'Dibajak',
                'status' => 'active',
            ])
            ->assertNotFound();

        $this->assertSame($student->full_name, $student->fresh()->full_name);
    }

    public function test_employee_from_another_tenant_cannot_be_linked_to_this_tenants_student(): void
    {
        $admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $otherSchool = School::factory()->create();
        $foreignGuardian = Guardian::factory()->create(['school_id' => $otherSchool->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($admin, $this->school)
            ->from("/students/{$student->id}")
            ->post("/students/{$student->id}/guardians", [
                'guardian_id' => $foreignGuardian->id,
                'relationship' => 'parent',
            ])
            ->assertSessionHasErrors('guardian_id');

        $this->assertDatabaseMissing('student_guardians', [
            'student_id' => $student->id,
        ]);
    }
}
