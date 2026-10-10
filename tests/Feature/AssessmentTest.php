<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentScore;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class AssessmentTest extends TestCase
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
     * Assessment context: teaching assignment + roster of students.
     *
     * @return array<string, mixed>
     */
    private function makeAssessmentContext(int $rosterCount = 3): array
    {
        $context = $this->makeAssignedTeacherContext($this->school);

        $students = Student::factory()->count($rosterCount)->create(['school_id' => $this->school->id]);
        $this->placeStudents($context['studyGroup'], $students->all(), '2026-07-01');

        $assignment = $context['subject']->teachingAssignments()->firstOrFail();
        $assignment->update(['semester_id' => $context['semester']->id]);
        $assignment->refresh();

        return [...$context, 'students' => $students, 'assignment' => $assignment];
    }

    public function test_admin_can_create_a_draft_assessment(): void
    {
        $ctx = $this->makeAssessmentContext();

        $this->actingInSchool($this->admin, $this->school)
            ->post('/assessments', [
                'teaching_assignment_id' => $ctx['assignment']->id,
                'title' => 'Quiz Bab 1',
                'assessment_date' => '2026-08-10',
                'max_score' => 100,
            ])
            ->assertRedirect();

        $assessment = Assessment::firstOrFail();

        $this->assertSame($this->school->id, $assessment->school_id);
        $this->assertSame('draft', $assessment->status->value);
        $this->assertSame($ctx['semester']->id, $assessment->semester_id, 'Semester mengikuti penugasan.');

        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.created']);
    }

    public function test_another_schools_assignment_cannot_be_used(): void
    {
        $ctx = $this->makeAssessmentContext();

        $otherSchool = School::factory()->create();
        $otherContext = $this->makeTeachingContext($otherSchool);
        $otherAssignment = TeachingAssignment::factory()
            ->forGroup(
                $otherContext['studyGroup'],
                Subject::factory()->create(['school_id' => $otherSchool->id]),
                $otherContext['teacher'],
            )
            ->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/assessments')
            ->post('/assessments', [
                'teaching_assignment_id' => $otherAssignment->id,
                'title' => 'Lintas sekolah',
                'assessment_date' => '2026-08-10',
                'max_score' => 100,
            ])
            ->assertSessionHasErrors('teaching_assignment_id');

        $this->assertSame(0, Assessment::count());
    }

    public function test_assigned_teacher_can_enter_scores_for_roster_students_only(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10']);

        $rosterStudent = $ctx['students'][0];
        $outsider = Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($ctx['user'], $this->school)
            ->post("/assessments/{$assessment->id}/scores", [
                'scores' => [
                    $rosterStudent->id => ['score' => 88],
                    $outsider->id => ['score' => 50],
                ],
            ])
            ->assertSessionHasErrors("scores.{$outsider->id}");

        $this->assertSame(0, $assessment->scores()->count(), 'Seluruh batch ditolak bila satu id tidak valid.');

        $this->actingInSchool($ctx['user'], $this->school)
            ->post("/assessments/{$assessment->id}/scores", [
                'scores' => [$rosterStudent->id => ['score' => 88, 'feedback' => 'Bagus']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_id' => $assessment->id,
            'student_id' => $rosterStudent->id,
            'score' => 88.00,
        ]);
    }

    public function test_score_above_max_is_rejected(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10', 'max_score' => 50]);

        $this->actingInSchool($ctx['user'], $this->school)
            ->post("/assessments/{$assessment->id}/scores", [
                'scores' => [$ctx['students'][0]->id => ['score' => 75]],
            ])
            ->assertSessionHasErrors("scores.{$ctx['students'][0]->id}.score");

        $this->assertSame(0, $assessment->scores()->count());
    }

    public function test_publishing_finalizes_scores_and_blocks_further_draft_saves(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10']);

        $student = $ctx['students'][0];

        $this->actingInSchool($ctx['user'], $this->school)
            ->post("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 90]]])
            ->assertRedirect();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/assessments/{$assessment->id}/publish")
            ->assertRedirect();

        $assessment->refresh();
        $this->assertSame('published', $assessment->status->value);

        $score = AssessmentScore::where('assessment_id', $assessment->id)->firstOrFail();
        $this->assertNotNull($score->finalized_at);

        $this->actingInSchool($ctx['user'], $this->school)
            ->post("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 99]]])
            ->assertForbidden();

        $this->assertSame(90.00, (float) $score->fresh()->score);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.published']);
    }

    public function test_publish_requires_finalized_permission(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10']);

        AssessmentScore::factory()
            ->forAssessment($assessment, $ctx['students'][0], 80.0)
            ->create(['school_id' => $this->school->id]);

        // The assigned teacher holds manage.assessments but not finalize.grades.
        $this->actingInSchool($ctx['user'], $this->school)
            ->post("/assessments/{$assessment->id}/publish")
            ->assertForbidden();

        $this->assertSame('draft', $assessment->fresh()->status->value);
    }

    public function test_publishing_with_no_scores_is_rejected(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10']);

        $this->actingInSchool($this->admin, $this->school)
            ->from("/assessments/{$assessment->id}")
            ->post("/assessments/{$assessment->id}/publish")
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $assessment->fresh()->status->value);
    }

    public function test_correction_keeps_previous_value_and_who_changed_it(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->published()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10']);

        $score = AssessmentScore::factory()
            ->forAssessment($assessment, $ctx['students'][0], 78.0, $ctx['user'])
            ->finalized()
            ->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->put("/assessment-scores/{$score->id}/correct", [
                'score' => 85,
                'reason' => 'Pemeriksaan ulang lembar jawaban',
            ])
            ->assertRedirect();

        $score->refresh();

        $this->assertSame(85.00, (float) $score->score);
        $this->assertSame(78.00, (float) $score->previous_score);
        $this->assertSame($this->admin->id, $score->corrected_by);
        $this->assertNotNull($score->corrected_at);
        $this->assertSame(1, $score->correction_count);

        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.score_corrected']);
    }

    public function test_correction_requires_correct_grades_permission(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->published()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10']);

        $score = AssessmentScore::factory()
            ->forAssessment($assessment, $ctx['students'][0], 78.0)
            ->finalized()
            ->create(['school_id' => $this->school->id]);

        // The assigned teacher holds manage.assessments but not correct.grades.
        $this->actingInSchool($ctx['user'], $this->school)
            ->put("/assessment-scores/{$score->id}/correct", ['score' => 99, 'reason' => 'curang'])
            ->assertForbidden();

        $this->assertSame(78.00, (float) $score->fresh()->score);
    }

    public function test_published_assessment_cannot_be_deleted(): void
    {
        $ctx = $this->makeAssessmentContext();

        $assessment = Assessment::factory()
            ->published()
            ->forAssignment($ctx['assignment'])
            ->create(['school_id' => $this->school->id, 'assessment_date' => '2026-08-10']);

        $this->actingInSchool($this->admin, $this->school)
            ->delete("/assessments/{$assessment->id}")
            ->assertForbidden();

        $this->assertNotNull($assessment->fresh());
    }

    public function test_cross_tenant_assessment_is_invisible(): void
    {
        $this->makeAssessmentContext();

        $otherSchool = School::factory()->create();
        $otherContext = $this->makeTeachingContext($otherSchool);
        $otherAssignment = TeachingAssignment::factory()
            ->forGroup(
                $otherContext['studyGroup'],
                Subject::factory()->create(['school_id' => $otherSchool->id]),
                $otherContext['teacher'],
            )
            ->create(['school_id' => $otherSchool->id]);

        $foreign = Assessment::factory()
            ->forAssignment($otherAssignment)
            ->create(['school_id' => $otherSchool->id, 'assessment_date' => '2026-08-10']);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/assessments/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_staff_cannot_create_assessments(): void
    {
        $ctx = $this->makeAssessmentContext();
        $staff = $this->makeUserWithRole($this->school, 'staff');
        $this->refreshPermissionCache();

        $this->actingInSchool($staff, $this->school)
            ->get('/assessments')->assertOk();

        $this->actingInSchool($staff, $this->school)
            ->post('/assessments', [
                'teaching_assignment_id' => $ctx['assignment']->id,
                'title' => 'Tidak Boleh',
                'assessment_date' => '2026-08-10',
                'max_score' => 100,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_manage_categories_and_teacher_cannot(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/assessment-categories', [
                'name' => 'Tugas Harian',
                'slug' => 'tugas-harian',
                'default_weight' => 30,
            ])
            ->assertRedirect();

        $category = AssessmentCategory::firstOrFail();

        $this->assertSame($this->school->id, $category->school_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment_category.created']);

        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->post('/assessment-categories', ['name' => 'Keren', 'slug' => 'keren'])
            ->assertForbidden();
    }

    public function test_category_slug_is_unique_per_school(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/assessment-categories', ['name' => 'Tugas', 'slug' => 'tugas'])
            ->assertRedirect();

        $this->actingInSchool($this->admin, $this->school)
            ->from('/assessment-categories')
            ->post('/assessment-categories', ['name' => 'Tugas Lagi', 'slug' => 'tugas'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, AssessmentCategory::count());
    }
}
