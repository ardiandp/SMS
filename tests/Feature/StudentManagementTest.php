<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class StudentManagementTest extends TestCase
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

    public function test_admin_can_create_student_with_status_history_and_audit(): void
    {
        $response = $this->actingInSchool($this->admin, $this->school)->post('/students', [
            'nis' => 'S1001',
            'nisn' => '0091234567',
            'full_name' => 'Budi Santoso',
            'birth_date' => '2012-05-10',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $student = Student::where('nis', 'S1001')->firstOrFail();
        $response->assertRedirect(route('students.show', $student));

        $this->assertSame($this->school->id, $student->school_id);
        $this->assertDatabaseHas('student_status_histories', [
            'student_id' => $student->id,
            'from_status' => null,
            'to_status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'student.created',
        ]);
    }

    public function test_duplicate_nis_in_same_school_is_rejected(): void
    {
        Student::factory()->create(['school_id' => $this->school->id, 'nis' => 'DUP01']);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/students/create')
            ->post('/students', [
                'nis' => 'DUP01',
                'full_name' => 'Duplikat',
                'status' => 'active',
            ])
            ->assertRedirect('/students/create')
            ->assertSessionHasErrors('nis');

        $this->assertSame(1, Student::where('nis', 'DUP01')->count());
    }

    public function test_same_nis_in_different_school_is_allowed(): void
    {
        $otherSchool = School::factory()->create();
        Student::factory()->create(['school_id' => $otherSchool->id, 'nis' => 'SAME01']);

        $this->actingInSchool($this->admin, $this->school)->post('/students', [
            'nis' => 'SAME01',
            'full_name' => 'Siswa Sekolah Lain',
            'status' => 'active',
        ])->assertRedirectContains('/students/');

        $this->assertSame(1, Student::where('school_id', $this->school->id)->where('nis', 'SAME01')->count());
    }

    public function test_cross_tenant_student_is_not_readable(): void
    {
        $otherSchool = School::factory()->create();
        $foreignStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/students/{$foreignStudent->id}")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->get("/students/{$foreignStudent->id}/edit")
            ->assertNotFound();
    }

    public function test_cross_tenant_student_update_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $foreignStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/students/{$foreignStudent->id}", [
                'nis' => $foreignStudent->nis,
                'full_name' => 'Hacked Name',
                'status' => 'active',
            ])
            ->assertNotFound();

        $this->assertSame($foreignStudent->full_name, $foreignStudent->fresh()->full_name);
    }
}
