<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\GraduationRecord;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * PRD-04: penerbitan catatan kelulusan.
 *
 * The graduation rule belongs to the school; the application records the
 * declaration with an audit trail and a dedicated permission.
 */
class GraduationRecordTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicYear $year;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');

        $context = $this->makeAcademicContext($this->school);
        $this->year = $context['academicYear'];
        $this->student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->refreshPermissionCache();
    }

    public function test_admin_issues_a_graduation_record_with_audit(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/promotions/graduations', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'document_number' => 'SKL/2026/001',
            ])
            ->assertRedirect(route('promotions.index', ['academic_year_id' => $this->year->id]));

        $record = GraduationRecord::firstOrFail();

        $this->assertSame($this->school->id, $record->school_id);
        $this->assertSame('issued', $record->status->value);
        $this->assertSame('SKL/2026/001', $record->document_number);
        $this->assertSame($this->admin->id, $record->issued_by);
        $this->assertNotNull($record->issued_at);

        $this->assertDatabaseHas('audit_logs', ['action' => 'graduation_record.issued']);
    }

    public function test_reissuing_replaces_the_document_number_and_audits_the_previous_one(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/promotions/graduations', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'document_number' => 'SKL/2026/001',
            ])
            ->assertRedirect();

        $this->actingInSchool($this->admin, $this->school)
            ->post('/promotions/graduations', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'document_number' => 'SKL/2026/002',
            ])
            ->assertRedirect();

        $this->assertSame(1, GraduationRecord::count(), 'Unique (student_id, academic_year_id): satu baris.');
        $this->assertSame('SKL/2026/002', GraduationRecord::firstOrFail()->document_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'graduation_record.reissued']);
    }

    public function test_document_number_is_required(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from('/promotions')
            ->post('/promotions/graduations', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
            ])
            ->assertSessionHasErrors('document_number');

        $this->assertSame(0, GraduationRecord::count());
    }

    public function test_student_from_another_tenant_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $foreignStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/promotions')
            ->post('/promotions/graduations', [
                'student_id' => $foreignStudent->id,
                'academic_year_id' => $this->year->id,
                'document_number' => 'SKL/2026/999',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(0, GraduationRecord::count());
    }

    public function test_teacher_cannot_issue_graduations(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->post('/promotions/graduations', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'document_number' => 'SKL/2026/001',
            ])
            ->assertForbidden();

        $this->assertSame(0, GraduationRecord::count());
    }

    public function test_cross_tenant_graduation_record_is_invisible(): void
    {
        $otherSchool = School::factory()->create();
        $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id]);
        $otherYear = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);

        GraduationRecord::factory()->create([
            'school_id' => $otherSchool->id,
            'student_id' => $otherStudent->id,
            'academic_year_id' => $otherYear->id,
            'status' => 'issued',
            'document_number' => 'SKL/OTHER/001',
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/promotions?academic_year_id={$otherYear->id}")
            ->assertOk()
            ->assertDontSee('SKL/OTHER/001');
    }
}
