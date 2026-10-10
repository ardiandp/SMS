<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\StudentStatusHistory;
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

    public function test_update_is_audited(): void
    {
        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'full_name' => 'Nama Lama',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/students/{$student->id}", [
                'nis' => $student->nis,
                'full_name' => 'Nama Baru',
                'status' => 'active',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertSame('Nama Baru', $student->fresh()->full_name);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'actor_user_id' => $this->admin->id,
            'action' => 'student.updated',
            'subject_id' => $student->id,
        ]);
    }

    public function test_updating_status_records_a_status_history_entry(): void
    {
        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'active',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/students/{$student->id}", [
                'nis' => $student->nis,
                'full_name' => $student->full_name,
                'status' => 'withdrawn',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertSame('withdrawn', $student->fresh()->status->value);

        $this->assertDatabaseHas('student_status_histories', [
            'student_id' => $student->id,
            'from_status' => 'active',
            'to_status' => 'withdrawn',
            'changed_by' => $this->admin->id,
        ]);
    }

    public function test_update_without_status_change_does_not_add_history(): void
    {
        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'active',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/students/{$student->id}", [
                'nis' => $student->nis,
                'full_name' => 'Nama Berubah Saja',
                'status' => 'active',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertSame(0, StudentStatusHistory::where('student_id', $student->id)->count());
    }

    public function test_destroy_soft_deletes_and_audits(): void
    {
        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'status' => 'active',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/students/{$student->id}")
            ->assertRedirect(route('students.index'));

        $this->assertSoftDeleted($student);
        $this->assertSame('inactive', $student->fresh()->status->value);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'student.deactivated',
            'subject_id' => $student->id,
        ]);

        $this->assertDatabaseHas('student_status_histories', [
            'student_id' => $student->id,
            'to_status' => 'inactive',
        ]);
    }

    public function test_soft_deleted_student_disappears_from_the_index(): void
    {
        $student = Student::factory()->create([
            'school_id' => $this->school->id,
            'full_name' => 'Siswa Dinonaktifkan',
        ]);
        $student->delete();

        $response = $this->actingInSchool($this->admin, $this->school)
            ->get('/students')
            ->assertOk();

        $response->assertDontSee('Siswa Dinonaktifkan');
    }

    public function test_a_guardian_from_the_same_school_can_be_linked(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$student->id}/guardians", [
                'guardian_id' => $guardian->id,
                'relationship' => 'mother',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertDatabaseHas('student_guardians', [
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'mother',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'student.guardian_linked',
        ]);
    }

    public function test_a_guardian_can_be_linked_to_multiple_students(): void
    {
        $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

        $first = Student::factory()->create(['school_id' => $this->school->id]);
        $second = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$first->id}/guardians", [
                'guardian_id' => $guardian->id,
                'relationship' => 'mother',
            ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$second->id}/guardians", [
                'guardian_id' => $guardian->id,
                'relationship' => 'mother',
            ]);

        $this->assertSame(2, StudentGuardian::where('guardian_id', $guardian->id)->count());
    }

    public function test_a_student_can_have_multiple_guardians(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $father = Guardian::factory()->create(['school_id' => $this->school->id]);
        $mother = Guardian::factory()->create(['school_id' => $this->school->id]);

        foreach ([$father, $mother] as $guardian) {
            $this->actingInSchool($this->admin, $this->school)
                ->post("/students/{$student->id}/guardians", [
                    'guardian_id' => $guardian->id,
                    'relationship' => 'parent',
                ])
                ->assertRedirect(route('students.show', $student));
        }

        $this->assertSame(2, StudentGuardian::where('student_id', $student->id)->count());
    }

    public function test_search_and_status_filter_are_server_side(): void
    {
        Student::factory()->create([
            'school_id' => $this->school->id,
            'full_name' => 'Zulfikar Pencarian',
            'status' => 'active',
        ]);
        Student::factory()->create([
            'school_id' => $this->school->id,
            'full_name' => 'ZulfikarKeluar',
            'status' => 'withdrawn',
        ]);

        $active = $this->actingInSchool($this->admin, $this->school)
            ->get('/students?q=Zulfikar&status=active')
            ->assertOk();

        $active->assertSee('Zulfikar Pencarian');
        $active->assertDontSee('ZulfikarKeluar');

        // Filter status yang tidak dikenal diabaikan, bukan error.
        $this->actingInSchool($this->admin, $this->school)
            ->get('/students?status=tidak-valid')
            ->assertOk();
    }
}
