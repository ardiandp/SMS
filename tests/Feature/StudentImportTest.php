<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class StudentImportTest extends TestCase
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

    /**
     * Build a CSV upload from raw lines.
     */
    private function csv(string $contents, string $name = 'siswa.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_preview_reports_errors_per_row_and_writes_nothing(): void
    {
        $file = $this->csv(
            "nis,full_name\n"
            ."S001,Budi Santoso\n"
            .",Tanpa NIS\n"
        );

        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', ['file' => $file])
            ->assertRedirect(route('students.import.create'))
            ->assertSessionHasNoErrors();

        // Preview belum menulis apa pun ke database.
        $this->assertSame(0, Student::count());

        $preview = session('import_preview');

        $this->assertCount(2, $preview['rows']);
        $this->assertSame([], $preview['rows'][0]['errors']);
        $this->assertContains('NIS wajib diisi.', $preview['rows'][1]['errors']);
    }

    public function test_missing_required_header_is_rejected(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', ['file' => $this->csv("nis\nS001\n")])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Student::count());
    }

    public function test_invalid_optional_values_are_reported_per_row(): void
    {
        $file = $this->csv(
            "nis,full_name,birth_date,gender,status\n"
            ."S002,Siti Aminah,not-a-date,male,active\n"
            ."S003,Budi,2012-01-01,unknown,active\n"
            ."S004,Dewi,2012-01-01,female,tidak-dikenal\n"
        );

        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', ['file' => $file]);

        $rows = session('import_preview')['rows'];

        $this->assertContains('Tanggal lahir tidak valid.', $rows[0]['errors']);
        $this->assertContains('Gender harus male/female.', $rows[1]['errors']);
        $this->assertContains('Status tidak dikenal.', $rows[2]['errors']);
    }

    public function test_duplicate_nis_inside_the_file_is_reported(): void
    {
        $file = $this->csv(
            "nis,full_name\n"
            ."S005,Budi\n"
            ."S005,Budi Duplikat\n"
        );

        $this->actingInSchool($this->admin, $this->school)->post('/students/import', ['file' => $file]);

        $rows = session('import_preview')['rows'];

        $this->assertSame([], $rows[0]['errors']);
        $this->assertNotSame([], $rows[1]['errors']);
        $this->assertSame(0, Student::count());
    }

    public function test_existing_nis_in_same_school_is_reported(): void
    {
        Student::factory()->create(['school_id' => $this->school->id, 'nis' => 'S006']);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', ['file' => $this->csv("nis,full_name\nS006,Sudah Ada\n")]);

        $rows = session('import_preview')['rows'];

        $this->assertNotSame([], $rows[0]['errors']);
    }

    public function test_nis_that_only_exists_in_another_school_is_not_a_conflict(): void
    {
        $otherSchool = School::factory()->create();
        Student::factory()->create(['school_id' => $otherSchool->id, 'nis' => 'S007']);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', ['file' => $this->csv("nis,full_name\nS007,Siswa Tenant Ini\n")]);

        $rows = session('import_preview')['rows'];

        $this->assertSame([], $rows[0]['errors']);
    }

    public function test_commit_with_error_rows_writes_nothing(): void
    {
        $file = $this->csv(
            "nis,full_name\n"
            ."S008,Budi\n"
            .",Tanpa NIS\n"
        );

        $this->actingInSchool($this->admin, $this->school)->post('/students/import', ['file' => $file]);
        $this->actingInSchool($this->admin, $this->school)->post('/students/import/commit');

        // All-or-nothing: baris yang valid pun tidak boleh ikut tertulis.
        $this->assertSame(0, Student::count());
    }

    public function test_commit_writes_all_rows_to_the_active_tenant_and_audits(): void
    {
        $file = $this->csv(
            "nis,full_name,nisn,birth_date,gender,status\n"
            ."S009,Budi Santoso,0091112223,2012-05-10,male,active\n"
            ."S010,Siti Aminah,0091112224,2012-07-01,female,active\n"
        );

        $this->actingInSchool($this->admin, $this->school)->post('/students/import', ['file' => $file]);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import/commit')
            ->assertRedirect(route('students.index'));

        $this->assertSame(2, Student::count());

        $this->assertDatabaseHas('students', [
            'school_id' => $this->school->id,
            'nis' => 'S009',
            'full_name' => 'Budi Santoso',
        ]);

        $this->assertDatabaseHas('students', [
            'school_id' => $this->school->id,
            'nis' => 'S010',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'actor_user_id' => $this->admin->id,
            'action' => 'students.imported',
        ]);
    }

    public function test_commit_uses_default_status_when_column_is_absent(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', ['file' => $this->csv("nis,full_name\nS011,Tanpa Kolom Status\n")]);

        $this->actingInSchool($this->admin, $this->school)->post('/students/import/commit');

        $student = Student::where('nis', 'S011')->firstOrFail();

        $this->assertSame('active', $student->status->value);
        $this->assertNull($student->birth_date);
        $this->assertNull($student->gender);
        $this->assertNull($student->nisn);
    }

    public function test_commit_without_preview_is_rejected(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import/commit')
            ->assertRedirect(route('students.import.create'))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Student::count());
    }

    public function test_cancel_discards_the_preview(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', ['file' => $this->csv("nis,full_name\nS012,Batal\n")]);

        $this->assertNotNull(session('import_preview'));

        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import/cancel')
            ->assertRedirect(route('students.import.create'));

        $this->assertNull(session('import_preview'));
    }

    public function test_user_without_import_permission_is_forbidden(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/students/import')
            ->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->post('/students/import/commit')
            ->assertForbidden();

        $this->assertSame(0, Student::count());
    }

    public function test_import_from_another_tenant_never_writes_across_tenants(): void
    {
        $otherSchool = School::factory()->create();
        $otherAdmin = $this->makeUserWithRole($otherSchool, 'school-admin');
        $this->refreshPermissionCache();

        $this->actingInSchool($otherAdmin, $otherSchool)
            ->post('/students/import', ['file' => $this->csv("nis,full_name\nS013,Asing\n")]);

        $this->actingInSchool($otherAdmin, $otherSchool)->post('/students/import/commit');

        $this->assertSame(0, Student::where('school_id', $this->school->id)->count());
        $this->assertSame(1, Student::where('school_id', $otherSchool->id)->count());
    }

    public function test_upload_requires_a_file(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/students/import', [])
            ->assertSessionHasErrors('file');
    }
}
