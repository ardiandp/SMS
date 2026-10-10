<?php

namespace Tests\Feature;

use App\Domain\Attendance\AttendanceReportService;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Recap totals and CSV export.
 *
 * The percentage definitions asserted here are the ones documented on
 * AttendanceReportService: attendance_rate counts only `present`, while
 * effective_attendance_rate also counts sick and permitted.
 */
class AttendanceReportTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private School $school;

    private User $admin;

    private StudyGroup $group;

    /** @var Collection<int, Student> */
    private $roster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = $this->makeUserWithRole($this->school, 'school-admin');
        $this->refreshPermissionCache();

        $this->group = $this->makeAcademicContext($this->school)['studyGroup'];

        // A stable roster so multi-day tests follow the same students.
        $this->roster = Student::factory()->count(5)->create(['school_id' => $this->school->id]);
        $this->placeStudents($this->group, $this->roster->all(), '2026-07-01');
    }

    /**
     * @param  array<int, AttendanceStatus>  $statuses  one entry per roster member
     */
    private function seedDay(string $date, array $statuses): void
    {
        $this->actingInSchool($this->admin, $this->school)->post('/attendance/sessions', [
            'study_group_id' => $this->group->id,
            'session_date' => $date,
        ])->assertRedirect();

        $session = AttendanceSession::whereDate('session_date', $date)->firstOrFail();

        $map = [];

        foreach ($statuses as $index => $status) {
            $recordId = $session->records()->where('student_id', $this->roster[$index]->id)->value('id');
            $map[$recordId] = $status->value;
        }

        $this->actingInSchool($this->admin, $this->school)
            ->post("/attendance/sessions/{$session->id}/mark", ['statuses' => $map])
            ->assertRedirect();
    }

    public function test_counts_always_sum_to_the_total(): void
    {
        $this->seedDay('2026-08-03', [
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Sick,
            AttendanceStatus::Permitted,
            AttendanceStatus::Absent,
        ]);

        $session = AttendanceSession::firstOrFail();
        $summary = app(AttendanceReportService::class)->forSession($session);

        $this->assertSame(5, $summary['total']);
        $this->assertSame(2, $summary['counts'][AttendanceStatus::Present->value]);
        $this->assertSame(1, $summary['counts'][AttendanceStatus::Sick->value]);
        $this->assertSame(1, $summary['counts'][AttendanceStatus::Permitted->value]);
        $this->assertSame(1, $summary['counts'][AttendanceStatus::Absent->value]);
        $this->assertSame($summary['total'], array_sum($summary['counts']));
    }

    public function test_attendance_rate_counts_only_present(): void
    {
        $this->seedDay('2026-08-03', [
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Sick,
            AttendanceStatus::Absent,
            AttendanceStatus::Absent,
        ]);

        $session = AttendanceSession::firstOrFail();
        $summary = app(AttendanceReportService::class)->forSession($session);

        // 2 present / 5 total
        $this->assertSame(40.0, $summary['attendance_rate']);
        // (2 present + 1 sick) / 5 total
        $this->assertSame(60.0, $summary['effective_attendance_rate']);
    }

    public function test_a_period_with_no_records_reports_null_not_zero(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $summary = app(AttendanceReportService::class)
            ->forStudent($student, '2026-01-01', '2026-12-31');

        $this->assertSame(0, $summary['total']);
        $this->assertNull($summary['attendance_rate']);
        $this->assertNull($summary['effective_attendance_rate']);
    }

    public function test_a_student_summary_spans_the_requested_range(): void
    {
        $this->seedDay('2026-08-03', [
            AttendanceStatus::Present,
            AttendanceStatus::Absent,
            AttendanceStatus::Absent,
            AttendanceStatus::Absent,
            AttendanceStatus::Absent,
        ]);
        $this->seedDay('2026-08-04', [
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Absent,
            AttendanceStatus::Absent,
        ]);

        // Roster member 1 is absent on day one and present on day two.
        $student = $this->roster[1];

        $summary = app(AttendanceReportService::class)
            ->forStudent($student, '2026-08-03', '2026-08-04');

        $this->assertSame(2, $summary['total']);
        $this->assertSame(50.0, $summary['attendance_rate']);
        $this->assertSame(50.0, $summary['effective_attendance_rate']);
    }

    public function test_the_report_only_contains_the_active_tenant(): void
    {
        $this->seedDay('2026-08-03', [AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Absent, AttendanceStatus::Absent, AttendanceStatus::Absent]);

        $otherSchool = School::factory()->create();
        $otherGroup = $this->makeAcademicContext($otherSchool)['studyGroup'];
        $foreignStudent = Student::factory()->create([
            'school_id' => $otherSchool->id,
            'full_name' => 'Siswa Tenant Asing',
        ]);

        $otherSession = AttendanceSession::factory()
            ->forGroup($otherGroup, $this->admin, '2026-08-03')
            ->create(['school_id' => $otherSchool->id]);

        AttendanceRecord::factory()
            ->forSession($otherSession, $this->admin)
            ->create(['student_id' => $foreignStudent->id]);

        $response = $this->actingInSchool($this->admin, $this->school)
            ->get('/attendance/reports?from=2026-08-01&to=2026-08-31')
            ->assertOk();

        $response->assertDontSee('Siswa Tenant Asing');

        $csv = app(AttendanceReportService::class)->toCsv(
            $this->school,
            '2026-08-01',
            '2026-08-31',
        );

        $this->assertStringNotContainsString('Siswa Tenant Asing', $csv);
    }

    public function test_the_report_page_renders(): void
    {
        $this->seedDay('2026-08-03', [AttendanceStatus::Present, AttendanceStatus::Absent]);

        $this->actingInSchool($this->admin, $this->school)
            ->get('/attendance/reports?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertSee($this->group->name);
    }

    public function test_an_admin_can_export_csv(): void
    {
        $this->seedDay('2026-08-03', [AttendanceStatus::Present, AttendanceStatus::Sick, AttendanceStatus::Absent, AttendanceStatus::Absent, AttendanceStatus::Absent]);

        $response = $this->actingInSchool($this->admin, $this->school)
            ->get('/attendance/reports/export?from=2026-08-01&to=2026-08-31')
            ->assertOk();

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertStringContainsString('Tanggal', $csv);
        $this->assertStringContainsString('Hadir', $csv);
        $this->assertStringContainsString('Sakit', $csv);
        $this->assertStringContainsString('2026-08-03', $csv);
    }

    public function test_export_requires_the_permission(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/attendance/reports/export?from=2026-08-01&to=2026-08-31')
            ->assertForbidden();
    }

    public function test_export_requires_a_valid_range(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->get('/attendance/reports/export?from=2026-08-31&to=2026-08-01')
            ->assertSessionHasErrors('to');

        $this->actingInSchool($this->admin, $this->school)
            ->get('/attendance/reports/export')
            ->assertSessionHasErrors(['from', 'to']);
    }

    public function test_a_teacher_can_read_reports_but_not_export_them(): void
    {
        $context = $this->makeAssignedTeacherContext($this->school);

        $this->actingInSchool($context['user'], $this->school)
            ->get('/attendance/reports?from=2026-08-01&to=2026-08-31')
            ->assertOk();

        $this->actingInSchool($context['user'], $this->school)
            ->get('/attendance/reports/export?from=2026-08-01&to=2026-08-31')
            ->assertForbidden();
    }

    public function test_a_report_from_another_tenant_is_not_reachable(): void
    {
        $otherSchool = School::factory()->create();
        $otherGroup = $this->makeAcademicContext($otherSchool)['studyGroup'];
        $foreignStudent = Student::factory()->create([
            'school_id' => $otherSchool->id,
            'full_name' => 'Siswa Asing',
        ]);

        $otherSession = AttendanceSession::factory()
            ->forGroup($otherGroup, $this->admin, '2026-08-03')
            ->create(['school_id' => $otherSchool->id]);

        AttendanceRecord::factory()
            ->forSession($otherSession, $this->admin)
            ->create(['student_id' => $foreignStudent->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/attendance/records/student/{$foreignStudent->id}")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->get("/attendance/records/group/{$otherGroup->id}")
            ->assertNotFound();
    }
}
