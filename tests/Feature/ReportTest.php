<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\School;
use App\Models\SchoolLetter;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

/**
 * PRD-05: laporan administratif generik — registry whitelist kolom, filter
 * periode, otorisasi per laporan, ekspor CSV berbatas, dan isolasi tenant.
 */
class ReportTest extends TestCase
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

    public function test_index_lists_only_reports_the_user_can_access(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->get('/reports')
            ->assertOk()
            ->assertSee('Rekap Siswa')
            ->assertSee('Log Surat Sekolah')
            ->assertSee('Rekap Pengumuman');

        // Guru hanya punya view.students: laporan surat/pengumuman tidak
        // ditawarkan.
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/reports')
            ->assertOk()
            ->assertSee('Rekap Siswa')
            ->assertDontSee('Log Surat Sekolah')
            ->assertDontSee('Rekap Pengumuman');
    }

    public function test_show_filters_by_period_and_selected_columns(): void
    {
        $inside = Student::factory()->create(['school_id' => $this->school->id, 'nis' => 'IN-001']);
        $inside->forceFill(['created_at' => '2026-03-10 08:00:00'])->save();

        $outside = Student::factory()->create(['school_id' => $this->school->id, 'nis' => 'OUT-001']);
        $outside->forceFill(['created_at' => '2026-08-10 08:00:00'])->save();

        $this->actingInSchool($this->admin, $this->school)
            ->get('/reports/students?'.http_build_query([
                'columns' => ['nis', 'full_name'],
                'from' => '2026-01-01',
                'to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertSee('IN-001')
            ->assertDontSee('OUT-001')
            ->assertSee('Nama'); // header kolom terpilih
    }

    public function test_unknown_report_key_is_not_found(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->get('/reports/users-password-table')
            ->assertNotFound();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        // Guru tidak punya manage.letters.
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $this->actingInSchool($teacher, $this->school)
            ->get('/reports/letters')
            ->assertForbidden();
    }

    public function test_unknown_column_is_rejected(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from('/reports/students')
            ->get('/reports/students?columns[]=password')
            ->assertSessionHasErrors('columns.0');
    }

    public function test_report_is_tenant_scoped(): void
    {
        $otherSchool = School::factory()->create();
        Student::factory()->create(['school_id' => $otherSchool->id, 'nis' => 'OTHER-1']);

        Student::factory()->create(['school_id' => $this->school->id, 'nis' => 'OWN-1']);

        $this->actingInSchool($this->admin, $this->school)
            ->get('/reports/students')
            ->assertOk()
            ->assertSee('OWN-1')
            ->assertDontSee('OTHER-1');
    }

    public function test_export_streams_csv_within_limit(): void
    {
        config()->set('reports.export_limit', 10);

        Student::factory()->create(['school_id' => $this->school->id, 'nis' => 'CSV-001']);

        $response = $this->actingInSchool($this->admin, $this->school)
            ->get('/reports/students/export?columns[]=nis&columns[]=full_name');

        $response->assertOk()->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('NIS,Nama', $csv);
        $this->assertStringContainsString('CSV-001', $csv);
    }

    public function test_export_is_blocked_when_result_exceeds_limit(): void
    {
        config()->set('reports.export_limit', 1);

        Student::factory()->create(['school_id' => $this->school->id]);
        Student::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/reports/students')
            ->get('/reports/students/export')
            ->assertSessionHasErrors('report');

        // Persempit lewat periode -> kembali di bawah batas -> ekspor jalan.
        $this->actingInSchool($this->admin, $this->school)
            ->get('/reports/students/export?from=2099-01-01')
            ->assertOk()
            ->assertDownload();
    }
}
