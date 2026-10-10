<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentScore;
use App\Models\AuditLog;
use App\Models\FinalGrade;
use App\Models\GradingPolicy;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class FinalGradeTest extends TestCase
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
     * Two weighted categories (Tugas 60, Ujian 40), one published assessment
     * each, one roster student with known scores.
     *
     * @return array<string, mixed>
     */
    private function makeGradingContext(): array
    {
        $ctx = $this->makeAssignedTeacherContext($this->school);

        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($ctx['studyGroup'], [$student], '2026-07-01');

        $assignment = $ctx['subject']->teachingAssignments()->firstOrFail();
        $assignment->update(['semester_id' => $ctx['semester']->id]);

        $tasks = AssessmentCategory::factory()->create([
            'school_id' => $this->school->id, 'name' => 'Tugas', 'slug' => 'tugas',
        ]);
        $exam = AssessmentCategory::factory()->create([
            'school_id' => $this->school->id, 'name' => 'Ujian', 'slug' => 'ujian',
        ]);

        return [
            ...$ctx,
            'student' => $student,
            'assignment' => $assignment,
            'tasksCategory' => $tasks,
            'examCategory' => $exam,
        ];
    }

    private function makeActivePolicy(array $ctx, array $weights, int $rounding = 2): GradingPolicy
    {
        $policy = GradingPolicy::factory()
            ->forYear($ctx['academicYear'])
            ->withConfig($weights, [
                ['label' => 'A', 'min' => 90],
                ['label' => 'B', 'min' => 80],
                ['label' => 'C', 'min' => 70],
                ['label' => 'D', 'min' => 60],
            ], $rounding)
            ->create(['school_id' => $this->school->id, 'name' => 'Kebijakan Utama']);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/grading-policies/{$policy->id}/activate")
            ->assertRedirect();

        return $policy->fresh();
    }

    /**
     * A published assessment with one score for the roster student.
     */
    private function makePublishedAssessment(array $ctx, AssessmentCategory $category, float $score, float $max = 100): Assessment
    {
        $assessment = Assessment::factory()
            ->published()
            ->forAssignment($ctx['assignment'], $category)
            ->create([
                'school_id' => $this->school->id,
                'assessment_date' => '2026-08-10',
                'max_score' => $max,
            ]);

        AssessmentScore::factory()
            ->forAssessment($assessment, $ctx['student'], $score)
            ->finalized()
            ->create(['school_id' => $this->school->id]);

        return $assessment;
    }

    public function test_weights_must_sum_to_100(): void
    {
        $ctx = $this->makeGradingContext();

        $this->actingInSchool($this->admin, $this->school)
            ->post('/grading-policies', [
                'name' => 'Kebijakan Utama',
                'academic_year_id' => $ctx['academicYear']->id,
                'config' => [
                    'weights' => [
                        $ctx['tasksCategory']->id => 30,
                        $ctx['examCategory']->id => 30,
                    ],
                    'grade_bands' => [['label' => 'A', 'min' => 90]],
                    'rounding' => 2,
                ],
            ])
            ->assertSessionHasErrors('config.weights');

        $this->assertSame(0, GradingPolicy::count());
    }

    public function test_activating_a_new_version_archives_the_previous_one(): void
    {
        $ctx = $this->makeGradingContext();
        $weights = [
            $ctx['tasksCategory']->id => 60,
            $ctx['examCategory']->id => 40,
        ];

        $first = $this->makeActivePolicy($ctx, $weights);

        $second = GradingPolicy::factory()
            ->forYear($ctx['academicYear'])
            ->withConfig($weights)
            ->create(['school_id' => $this->school->id, 'name' => 'Kebijakan Utama', 'version' => 2]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/grading-policies/{$second->id}/activate")
            ->assertRedirect();

        $this->assertSame('archived', $first->fresh()->status->value);
        $this->assertSame('active', $second->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'grading_policy.activated']);
    }

    public function test_calculation_is_deterministic_and_traceable(): void
    {
        $ctx = $this->makeGradingContext();
        $policy = $this->makeActivePolicy($ctx, [
            $ctx['tasksCategory']->id => 60,
            $ctx['examCategory']->id => 40,
        ]);

        // Tugas: 90/100 → 90%; Ujian: 80/100 → 80%. Weighted: 90*0.6 + 80*0.4 = 86.
        $this->makePublishedAssessment($ctx, $ctx['tasksCategory'], 90);
        $this->makePublishedAssessment($ctx, $ctx['examCategory'], 80);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/teaching-assignments/{$ctx['assignment']->id}/final-grades/calculate", [
                'grading_policy_id' => $policy->id,
            ])
            ->assertRedirect();

        $grade = FinalGrade::firstOrFail();

        $this->assertSame(86.00, (float) $grade->numeric_score);
        $this->assertSame('B', $grade->grade_label);
        $this->assertSame('Kebijakan Utama v1', $grade->calculation_version);
        $this->assertEqualsWithDelta(
            [$ctx['tasksCategory']->id => 60.0, $ctx['examCategory']->id => 40.0],
            $grade->calculation_detail_json['weights_used'],
            0.001,
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'final_grade.calculated']);
    }

    public function test_draft_assessments_are_excluded_from_the_calculation(): void
    {
        $ctx = $this->makeGradingContext();
        $policy = $this->makeActivePolicy($ctx, [
            $ctx['tasksCategory']->id => 60,
            $ctx['examCategory']->id => 40,
        ]);

        $this->makePublishedAssessment($ctx, $ctx['tasksCategory'], 90);

        // Draft assessment with a score must not influence the final grade.
        $draft = Assessment::factory()
            ->forAssignment($ctx['assignment'], $ctx['examCategory'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-11']);
        AssessmentScore::factory()
            ->forAssessment($draft, $ctx['student'], 10)
            ->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/teaching-assignments/{$ctx['assignment']->id}/final-grades/calculate", [
                'grading_policy_id' => $policy->id,
            ])
            ->assertRedirect();

        $grade = FinalGrade::firstOrFail();

        // Normalized over the 60% that has scores → just the Tugas percentage.
        $this->assertSame(90.00, (float) $grade->numeric_score);
        $this->assertEqualsWithDelta(
            [$ctx['tasksCategory']->id => 60.0],
            $grade->calculation_detail_json['weights_used'],
            0.001,
        );
    }

    public function test_rounding_follows_the_policy(): void
    {
        $ctx = $this->makeGradingContext();
        $policy = $this->makeActivePolicy($ctx, [
            $ctx['tasksCategory']->id => 60,
            $ctx['examCategory']->id => 40,
        ], rounding: 0);

        // 85*0.6 + 84.5*0.4 = 84.8 → rounds to 85 with 0 decimals.
        $this->makePublishedAssessment($ctx, $ctx['tasksCategory'], 85);
        $this->makePublishedAssessment($ctx, $ctx['examCategory'], 84.5);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/teaching-assignments/{$ctx['assignment']->id}/final-grades/calculate", [
                'grading_policy_id' => $policy->id,
            ])
            ->assertRedirect();

        $this->assertSame(85.00, (float) FinalGrade::firstOrFail()->numeric_score);
    }

    public function test_finalized_grades_are_not_recalculated(): void
    {
        $ctx = $this->makeGradingContext();
        $policy = $this->makeActivePolicy($ctx, [
            $ctx['tasksCategory']->id => 60,
            $ctx['examCategory']->id => 40,
        ]);

        $this->makePublishedAssessment($ctx, $ctx['tasksCategory'], 90);
        $this->makePublishedAssessment($ctx, $ctx['examCategory'], 80);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/teaching-assignments/{$ctx['assignment']->id}/final-grades/calculate", [
                'grading_policy_id' => $policy->id,
            ])
            ->assertRedirect();

        $grade = FinalGrade::firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/final-grades/{$grade->id}/finalize")
            ->assertRedirect();

        $this->assertSame('finalized', $grade->fresh()->status->value);

        // A new score arrives; recalculation must leave the finalized row alone.
        $this->makePublishedAssessment($ctx, $ctx['tasksCategory'], 100);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/teaching-assignments/{$ctx['assignment']->id}/final-grades/calculate", [
                'grading_policy_id' => $policy->id,
            ])
            ->assertRedirect();

        $this->assertSame(86.00, (float) $grade->fresh()->numeric_score);
    }

    public function test_correction_requires_correct_grades_and_keeps_audit_trail(): void
    {
        $ctx = $this->makeGradingContext();
        $policy = $this->makeActivePolicy($ctx, [
            $ctx['tasksCategory']->id => 60,
            $ctx['examCategory']->id => 40,
        ]);

        $this->makePublishedAssessment($ctx, $ctx['tasksCategory'], 90);
        $this->makePublishedAssessment($ctx, $ctx['examCategory'], 80);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/teaching-assignments/{$ctx['assignment']->id}/final-grades/calculate", [
                'grading_policy_id' => $policy->id,
            ])
            ->assertRedirect();

        $grade = FinalGrade::firstOrFail();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/final-grades/{$grade->id}/finalize")
            ->assertRedirect();

        // The assigned teacher may not correct finalized grades.
        $this->actingInSchool($ctx['user'], $this->school)
            ->put("/final-grades/{$grade->id}/correct", [
                'numeric_score' => 99, 'reason' => 'curang',
            ])
            ->assertForbidden();

        $this->actingInSchool($this->admin, $this->school)
            ->put("/final-grades/{$grade->id}/correct", [
                'numeric_score' => 90,
                'grade_label' => 'A',
                'reason' => 'Koreksi ketikan nilai rapor',
            ])
            ->assertRedirect();

        $grade->refresh();
        $this->assertSame(90.00, (float) $grade->numeric_score);
        $this->assertSame('A', $grade->grade_label);
        $this->assertNotNull($grade->corrected_at);

        $log = AuditLog::where('action', 'final_grade.corrected')->first();
        $this->assertNotNull($log);
        $this->assertSame(86.0, (float) $log->metadata_json['previous_score']);
        $this->assertSame(90.0, (float) $log->metadata_json['new_score']);
    }
}
