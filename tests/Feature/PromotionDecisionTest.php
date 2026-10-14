<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\PromotionDecision;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * PRD-04: keputusan kenaikan kelas & kelulusan.
 *
 * The application records the school's own deliberation outcome; it never
 * derives it from grades. Tests therefore cover authorization, tenant
 * consistency, structural coherence (target grade level rules) and the audit
 * trail — not an invented promotion formula.
 */
class PromotionDecisionTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicYear $year;

    private GradeLevel $grade7;

    private GradeLevel $grade8;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');

        $context = $this->makeAcademicContext($this->school);
        $this->year = $context['academicYear'];
        $this->grade7 = $context['gradeLevel'];
        $this->grade8 = GradeLevel::factory()->level('SMP', 8)->create(['school_id' => $this->school->id]);
        $this->student = Student::factory()->create(['school_id' => $this->school->id]);

        $this->refreshPermissionCache();
    }

    public function test_admin_records_a_promotion_decision_with_audit(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'promoted',
                'from_grade_level_id' => $this->grade7->id,
                'to_grade_level_id' => $this->grade8->id,
            ])
            ->assertRedirect(route('promotions.index', ['academic_year_id' => $this->year->id]));

        $decision = PromotionDecision::firstOrFail();

        $this->assertSame($this->school->id, $decision->school_id);
        $this->assertSame('promoted', $decision->decision->value);
        $this->assertSame($this->grade8->id, $decision->to_grade_level_id);
        $this->assertSame($this->admin->id, $decision->decided_by);
        $this->assertNotNull($decision->decided_at);

        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion_decision.recorded']);
    }

    public function test_promoted_without_target_grade_level_is_rejected(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from('/promotions')
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'promoted',
            ])
            ->assertSessionHasErrors('to_grade_level_id');

        $this->assertSame(0, PromotionDecision::count());
    }

    public function test_graduated_must_not_have_a_target_grade_level(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from('/promotions')
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'graduated',
                'to_grade_level_id' => $this->grade8->id,
            ])
            ->assertSessionHasErrors('to_grade_level_id');

        $this->assertSame(0, PromotionDecision::count());
    }

    public function test_graduated_without_target_grade_level_is_accepted(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'graduated',
            ])
            ->assertRedirect();

        $this->assertSame('graduated', PromotionDecision::firstOrFail()->decision->value);
    }

    public function test_redeliberation_updates_the_same_row_and_audits_the_previous_value(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'promoted',
                'from_grade_level_id' => $this->grade7->id,
                'to_grade_level_id' => $this->grade8->id,
            ])
            ->assertRedirect();

        $this->actingInSchool($this->admin, $this->school)
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'repeated',
                'from_grade_level_id' => $this->grade7->id,
                'to_grade_level_id' => $this->grade7->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, PromotionDecision::count(), 'Satu baris per siswa per tahun ajaran.');
        $this->assertSame('repeated', PromotionDecision::firstOrFail()->decision->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion_decision.revised']);
    }

    public function test_academic_year_from_another_tenant_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $foreignYear = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/promotions')
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $foreignYear->id,
                'decision' => 'promoted',
                'to_grade_level_id' => $this->grade8->id,
            ])
            ->assertSessionHasErrors('academic_year_id');

        $this->assertSame(0, PromotionDecision::count());
    }

    public function test_grade_level_from_another_tenant_is_rejected(): void
    {
        $otherSchool = School::factory()->create();
        $foreignLevel = GradeLevel::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/promotions')
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'promoted',
                'to_grade_level_id' => $foreignLevel->id,
            ])
            ->assertSessionHasErrors('to_grade_level_id');

        $this->assertSame(0, PromotionDecision::count());
    }

    public function test_teacher_cannot_record_promotion_decisions(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/promotions')
            ->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->post('/promotions/decisions', [
                'student_id' => $this->student->id,
                'academic_year_id' => $this->year->id,
                'decision' => 'promoted',
                'to_grade_level_id' => $this->grade8->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, PromotionDecision::count());
    }
}
