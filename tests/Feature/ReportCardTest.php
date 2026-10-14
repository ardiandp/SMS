<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\FinalGrade;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class ReportCardTest extends TestCase
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
     * A student with one finalized final grade, ready to publish.
     *
     * @return array<string, mixed>
     */
    private function makePublishableContext(): array
    {
        $ctx = $this->makeAssignedTeacherContext($this->school);

        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $this->placeStudents($ctx['studyGroup'], [$student], '2026-07-01');

        $assignment = $ctx['subject']->teachingAssignments()->firstOrFail();
        $assignment->update(['semester_id' => $ctx['semester']->id]);

        FinalGrade::factory()
            ->forContext($student, $assignment, $ctx['semester'])
            ->finalized()
            ->create([
                'school_id' => $this->school->id,
                'numeric_score' => 88.00,
                'grade_label' => 'B',
                'calculation_version' => 'Kebijakan Utama v1',
            ]);

        return [...$ctx, 'student' => $student, 'assignment' => $assignment];
    }

    private function publish(array $ctx): ReportCard
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/report-cards/publish', [
                'student_id' => $ctx['student']->id,
                'academic_year_id' => $ctx['academicYear']->id,
                'semester_id' => $ctx['semester']->id,
            ])
            ->assertRedirect();

        return ReportCard::query()->orderByDesc('revision_no')->firstOrFail();
    }

    public function test_publishing_snapshots_finalized_grades(): void
    {
        $ctx = $this->makePublishableContext();

        $card = $this->publish($ctx);

        $this->assertSame($this->school->id, $card->school_id);
        $this->assertSame(1, $card->revision_no);
        $this->assertNotNull($card->published_at);

        $item = $card->items()->firstOrFail();
        $this->assertSame($ctx['subject']->id, $item->subject_id);
        $this->assertSame(88.0, (float) $item->final_grade_snapshot_json['numeric_score']);
        $this->assertSame('Kebijakan Utama v1', $item->final_grade_snapshot_json['calculation_version']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'report_card.published']);
    }

    public function test_revision_creates_new_row_and_keeps_history(): void
    {
        $ctx = $this->makePublishableContext();

        $first = $this->publish($ctx);
        $second = $this->publish($ctx);

        $this->assertSame(1, $first->revision_no);
        $this->assertSame(2, $second->revision_no);
        $this->assertSame(2, ReportCard::count(), 'Revisi menambah baris, bukan menimpa.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'report_card.revised']);
    }

    public function test_published_snapshot_is_not_affected_by_later_grade_changes(): void
    {
        $ctx = $this->makePublishableContext();

        $card = $this->publish($ctx);

        // A correction lands on the final grade after publication.
        $grade = FinalGrade::firstOrFail();
        $grade->numeric_score = 95.00;
        $grade->save();

        $item = $card->items()->firstOrFail()->fresh();

        $this->assertSame(88.0, (float) $item->final_grade_snapshot_json['numeric_score'],
            'Snapshot rapor tidak boleh berubah setelah penerbitan.');
    }

    public function test_publishing_without_finalized_grades_is_rejected(): void
    {
        $ctx = $this->makePublishableContext();
        FinalGrade::query()->update(['status' => 'draft']);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/report-cards')
            ->post('/report-cards/publish', [
                'student_id' => $ctx['student']->id,
                'academic_year_id' => $ctx['academicYear']->id,
                'semester_id' => $ctx['semester']->id,
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(0, ReportCard::count());
    }

    public function test_teacher_cannot_publish_report_cards(): void
    {
        $ctx = $this->makePublishableContext();

        $this->actingInSchool($ctx['user'], $this->school)
            ->post('/report-cards/publish', [
                'student_id' => $ctx['student']->id,
                'academic_year_id' => $ctx['academicYear']->id,
                'semester_id' => $ctx['semester']->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, ReportCard::count());
    }

    public function test_cross_tenant_report_card_is_invisible(): void
    {
        $this->makePublishableContext();

        $otherSchool = School::factory()->create();
        $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id]);
        $otherYear = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);
        $otherSemester = Semester::factory()->forYear($otherYear)->create(['school_id' => $otherSchool->id]);

        $foreign = ReportCard::factory()
            ->forContext($otherStudent, $otherYear, $otherSemester)
            ->published()
            ->create(['school_id' => $otherSchool->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/report-cards/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_printing_a_report_card_requires_the_export_permission(): void
    {
        $ctx = $this->makePublishableContext();
        $card = $this->publish($ctx);

        // Guru memiliki view.grades tetapi bukan export.report-cards:
        // membaca di layar boleh, membawa keluar dokumen tidak.
        $this->actingInSchool($ctx['user'], $this->school)
            ->get("/report-cards/{$card->id}/print")
            ->assertForbidden();

        $this->actingInSchool($this->admin, $this->school)
            ->get("/report-cards/{$card->id}/print")
            ->assertOk();
    }
}
