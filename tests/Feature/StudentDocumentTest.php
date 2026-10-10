<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class StudentDocumentTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->refreshPermissionCache();
    }

    public function test_admin_can_upload_a_document_to_the_private_disk(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$this->student->id}/documents", [
                'file' => UploadedFile::fake()->create('akta.pdf', 120, 'application/pdf'),
                'document_type' => 'birth_certificate',
            ])
            ->assertRedirect(route('students.show', $this->student));

        $document = StudentDocument::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame($this->school->id, $document->school_id);
        $this->assertSame($this->admin->id, $document->uploaded_by);

        Storage::disk('local')->assertExists($document->storage_path);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'document.uploaded',
            'subject_id' => $document->id,
        ]);
    }

    public function test_upload_rejects_disallowed_file_types(): void
    {
        // Ekstensi .csv bukan bagian dari mimes yang diizinkan pada request.
        $this->actingInSchool($this->admin, $this->school)
            ->from("/students/{$this->student->id}")
            ->post("/students/{$this->student->id}/documents", [
                'file' => UploadedFile::fake()->create('data.csv', 10, 'text/csv'),
                'document_type' => 'other',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, StudentDocument::where('student_id', $this->student->id)->count());
    }

    public function test_upload_rejects_files_over_the_size_limit(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from("/students/{$this->student->id}")
            ->post("/students/{$this->student->id}/documents", [
                // 5 MB = 5120 KB adalah batas; 6000 KB harus ditolak.
                'file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
                'document_type' => 'other',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, StudentDocument::where('student_id', $this->student->id)->count());
    }

    public function test_document_requires_a_type(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from("/students/{$this->student->id}")
            ->post("/students/{$this->student->id}/documents", [
                'file' => UploadedFile::fake()->create('akta.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('document_type');
    }

    public function test_authorized_user_can_download_a_document(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$this->student->id}/documents", [
                'file' => UploadedFile::fake()->create('akta.pdf', 5, 'application/pdf'),
                'document_type' => 'birth_certificate',
            ]);

        $document = StudentDocument::where('student_id', $this->student->id)->firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->get("/documents/{$document->id}/download")
            ->assertOk();
    }

    public function test_downloading_a_document_from_another_tenant_is_not_found(): void
    {
        $otherSchool = School::factory()->create();
        $foreignDocument = StudentDocument::factory()->forStudent(
            Student::factory()->create(['school_id' => $otherSchool->id]),
            $this->admin,
        )->create();

        $this->actingInSchool($this->admin, $this->school)
            ->get("/documents/{$foreignDocument->id}/download")
            ->assertNotFound();
    }

    public function test_admin_of_another_school_cannot_download_this_tenants_document(): void
    {
        $outsiderSchool = School::factory()->create();
        $outsider = $this->makeUserWithRole($outsiderSchool, 'school-admin');
        $this->refreshPermissionCache();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$this->student->id}/documents", [
                'file' => UploadedFile::fake()->create('akta.pdf', 5, 'application/pdf'),
                'document_type' => 'birth_certificate',
            ]);

        $document = StudentDocument::where('student_id', $this->student->id)->firstOrFail();

        // Admin sekolah lain tetap tidak boleh, meski sama-sama punya peran admin.
        $this->actingInSchool($outsider, $outsiderSchool)
            ->get("/documents/{$document->id}/download")
            ->assertNotFound();
    }

    public function test_documents_are_not_reachable_through_a_public_url(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$this->student->id}/documents", [
                'file' => UploadedFile::fake()->create('akta.pdf', 5, 'application/pdf'),
                'document_type' => 'birth_certificate',
            ]);

        $document = StudentDocument::where('student_id', $this->student->id)->firstOrFail();

        // Disk 'local' berarti storage/app/private: tidak ada symlink /storage,
        // jadi path file tidak boleh pernah dilayani sebagai response sukses.
        $this->get('/storage/'.$document->storage_path)->assertStatus(403);
        $this->assertFileDoesNotExist(public_path('storage/'.$document->storage_path));
    }

    public function test_upload_requires_manage_documents_permission(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->post("/students/{$this->student->id}/documents", [
                'file' => UploadedFile::fake()->create('akta.pdf', 5, 'application/pdf'),
                'document_type' => 'birth_certificate',
            ])
            ->assertForbidden();

        $this->assertSame(0, StudentDocument::where('student_id', $this->student->id)->count());
    }

    public function test_upload_to_another_tenants_student_is_not_found(): void
    {
        $otherSchool = School::factory()->create();
        $foreignStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/students/{$foreignStudent->id}/documents", [
                'file' => UploadedFile::fake()->create('akta.pdf', 5, 'application/pdf'),
                'document_type' => 'birth_certificate',
            ])
            ->assertNotFound();

        $this->assertSame(0, StudentDocument::where('student_id', $foreignStudent->id)->count());
    }
}
