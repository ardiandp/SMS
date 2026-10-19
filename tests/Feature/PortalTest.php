<?php

namespace Tests\Feature;

use App\Domain\Portal\PortalLinkService;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Portal siswa & orang tua (PRD-06): relasi akun terverifikasi di server
 * pada setiap request, draft tidak tampil, IDOR dan lintas tenant ditolak,
 * pencabutan akses berlaku langsung.
 */
class PortalTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private function linkStudent(School $school, Student $student): \App\Models\User
    {
        $account = $this->makeUserWithRole($school, 'student');
        $admin = $this->makeUserWithRole($school, 'school-admin');

        // PortalLinkService membaca sekolah aktif dari CurrentSchool —
        // set konteks tenant dulu sebelum memanggil link.
        $this->actingInSchool($admin, $school);
        app(PortalLinkService::class)->linkStudent($student, $account, $admin);

        return $account;
    }

    private function linkGuardian(School $school, Guardian $guardian): \App\Models\User
    {
        $account = $this->makeUserWithRole($school, 'parent');
        $admin = $this->makeUserWithRole($school, 'school-admin');

        $this->actingInSchool($admin, $school);
        app(PortalLinkService::class)->linkGuardian($guardian, $account, $admin);

        return $account;
    }

    public function test_guardian_sees_linked_child(): void
    {
        $school = \App\Models\School::factory()->create();
        $child = Student::factory()->create(['school_id' => $school->id]);
        $guardian = Guardian::factory()->create(['school_id' => $school->id]);
        StudentGuardian::factory()->linkedTo($child, $guardian)->create();

        $parent = $this->linkGuardian($school, $guardian);
        $this->actingInSchool($parent, $school);

        $this->get(route('portal.grades', $child))
            ->assertOk()
            ->assertSee($child->full_name);
    }

    public function test_guardian_cannot_see_unrelated_child(): void
    {
        $school = \App\Models\School::factory()->create();
        $mine = Student::factory()->create(['school_id' => $school->id]);
        $other = Student::factory()->create(['school_id' => $school->id]);
        $guardian = Guardian::factory()->create(['school_id' => $school->id]);
        StudentGuardian::factory()->linkedTo($mine, $guardian)->create();

        $parent = $this->linkGuardian($school, $guardian);
        $this->actingInSchool($parent, $school);

        // Wali multi-anak boleh berpindah profil (?student=), tetapi profil
        // anak yang tidak terhubung ditolak 403 — bukan data orang lain.
        $this->get(route('portal.index', ['student' => $other->id]))->assertForbidden();
        $this->get(route('portal.grades', $other))->assertForbidden();
        $this->get(route('portal.index', ['student' => $mine->id]))->assertOk();
    }

    public function test_student_cannot_see_another_student(): void
    {
        $school = \App\Models\School::factory()->create();
        $me = Student::factory()->create(['school_id' => $school->id]);
        $other = Student::factory()->create(['school_id' => $school->id]);

        $student = $this->linkStudent($school, $me);
        $this->actingInSchool($student, $school);

        $this->get(route('portal.grades', $me))->assertOk();
        $this->get(route('portal.grades', $other))->assertForbidden();
        $this->get(route('portal.attendance', $other))->assertForbidden();
    }

    public function test_unpublished_grades_and_reports_are_hidden(): void
    {
        $school = \App\Models\School::factory()->create();
        $ctx = $this->makeTeachingContext($school);
        $subject = \App\Models\Subject::factory()->create(['school_id' => $school->id]);
        $subject2 = \App\Models\Subject::factory()->create(['school_id' => $school->id]);
        $assignment = \App\Models\TeachingAssignment::factory()
            ->forGroup($ctx['studyGroup'], $subject, $ctx['teacher'], $ctx['semester'])
            ->create(['school_id' => $school->id]);
        $assignment2 = \App\Models\TeachingAssignment::factory()
            ->forGroup($ctx['studyGroup'], $subject2, $ctx['teacher'], $ctx['semester'])
            ->create(['school_id' => $school->id]);

        $child = Student::factory()->create(['school_id' => $school->id]);

        // Satu draft (default factory), satu final — hanya final yang tampil.
        // Assignment berbeda karena unique (student, assignment, semester).
        \App\Models\FinalGrade::factory()->forContext($child, $assignment)->create([
            'numeric_score' => 43.21,
        ]);
        \App\Models\FinalGrade::factory()->forContext($child, $assignment2)->finalized()->create([
            'numeric_score' => 87.65,
        ]);

        // Satu rapor draft, satu terbit — hanya yang terbit tampil.
        // revision_no berbeda karena unique (student, tahun, semester, revisi).
        \App\Models\ReportCard::factory()->forContext($child, $ctx['academicYear'], $ctx['semester'])->create();
        $published = \App\Models\ReportCard::factory()
            ->forContext($child, $ctx['academicYear'], $ctx['semester'])
            ->published()
            ->create(['revision_no' => 2]);

        $guardian = Guardian::factory()->create(['school_id' => $school->id]);
        StudentGuardian::factory()->linkedTo($child, $guardian)->create();
        $parent = $this->linkGuardian($school, $guardian);
        $this->actingInSchool($parent, $school);

        $this->get(route('portal.grades', $child))
            ->assertOk()
            ->assertSee('87.65')
            ->assertDontSee('43.21');

        $this->get(route('portal.report-cards', $child))
            ->assertOk()
            ->assertSee($published->semester->academicYear->name);
    }

    public function test_revoked_account_loses_portal_access(): void
    {
        $school = \App\Models\School::factory()->create();
        $child = Student::factory()->create(['school_id' => $school->id]);
        $student = $this->linkStudent($school, $child);
        $admin = $this->makeUserWithRole($school, 'school-admin');

        $this->actingInSchool($student, $school);
        $this->get(route('portal.grades', $child))->assertOk();

        app(PortalLinkService::class)->revoke($student, $admin);

        $this->assertDatabaseMissing('student_user_links', ['user_id' => $student->id]);

        // Setelah revoke: daftar profil kosong, halaman data ditolak 403.
        $this->get(route('portal.index'))->assertOk()->assertSee('belum terhubung');
        $this->get(route('portal.grades', $child))->assertForbidden();
    }

    public function test_portal_is_isolated_between_tenants(): void
    {
        $schoolA = \App\Models\School::factory()->create();
        $schoolB = \App\Models\School::factory()->create();
        $childA = Student::factory()->create(['school_id' => $schoolA->id]);

        $student = $this->linkStudent($schoolA, $childA);

        // Sesi dipaksa ke tenant B padahal akun bukan anggota B: middleware
        // ResolveSchoolContext harus mengembalikan ke pilih sekolah.
        $this->actingAs($student)
            ->withSession(['current_school_id' => $schoolB->id])
            ->get(route('portal.grades', $childA))
            ->assertRedirect(route('schools.select'));
    }
}
