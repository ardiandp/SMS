<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\InteractsWithTenancy;
use Tests\TestCase;

class AcademicYearTest extends TestCase
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

    public function test_admin_can_create_an_academic_year(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->post('/academic-years', [
                'name' => '2026/2027',
                'starts_on' => '2026-07-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertRedirect(route('academic-years.index'));

        $this->assertDatabaseHas('academic_years', [
            'school_id' => $this->school->id,
            'name' => '2026/2027',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'action' => 'academic_year.created',
        ]);
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        $this->actingInSchool($this->admin, $this->school)
            ->from('/academic-years/create')
            ->post('/academic-years', [
                'name' => '2026/2027',
                'starts_on' => '2027-06-30',
                'ends_on' => '2026-07-01',
            ])
            ->assertSessionHasErrors('ends_on');

        $this->assertSame(0, AcademicYear::count());
    }

    public function test_the_database_also_rejects_reversed_dates(): void
    {
        // Bypass validation to prove the CHECK constraint exists in the schema.
        $this->expectException(QueryException::class);

        DB::table('academic_years')->insert([
            'school_id' => $this->school->id,
            'name' => 'Terbalik',
            'starts_on' => '2026-12-31',
            'ends_on' => '2026-01-01',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_duplicate_name_in_same_school_is_rejected(): void
    {
        AcademicYear::factory()->named('2026/2027')->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->from('/academic-years/create')
            ->post('/academic-years', [
                'name' => '2026/2027',
                'starts_on' => '2026-07-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_same_name_in_another_school_is_allowed(): void
    {
        $other = School::factory()->create();
        AcademicYear::factory()->named('2026/2027')->create(['school_id' => $other->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/academic-years', [
                'name' => '2026/2027',
                'starts_on' => '2026-07-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertRedirect(route('academic-years.index'));

        $this->assertSame(1, AcademicYear::where('school_id', $this->school->id)->count());
    }

    /**
     * PRD-02: a school has at most one active academic year. No partial unique
     * index exists for this, so the service enforces it.
     */
    public function test_only_one_academic_year_can_be_active_per_school(): void
    {
        $first = AcademicYear::factory()->named('2025/2026')->active()->create([
            'school_id' => $this->school->id,
        ]);

        $second = AcademicYear::factory()->named('2026/2027')->create([
            'school_id' => $this->school->id,
        ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/academic-years/{$second->id}/activate");

        $this->assertTrue($second->fresh()->is_active);
        $this->assertFalse($first->fresh()->is_active);

        $this->assertSame(
            1,
            AcademicYear::where('school_id', $this->school->id)->where('is_active', true)->count(),
        );
    }

    public function test_creating_an_active_year_deactivates_the_previous_one(): void
    {
        AcademicYear::factory()->named('2025/2026')->active()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post('/academic-years', [
                'name' => '2026/2027',
                'starts_on' => '2026-07-01',
                'ends_on' => '2027-06-30',
                'is_active' => '1',
            ]);

        $this->assertSame(
            1,
            AcademicYear::where('school_id', $this->school->id)->where('is_active', true)->count(),
        );
    }

    public function test_activating_a_year_does_not_touch_other_schools(): void
    {
        $other = School::factory()->create();
        $otherActive = AcademicYear::factory()->named('2025/2026')->active()->create([
            'school_id' => $other->id,
        ]);

        $mine = AcademicYear::factory()->named('2026/2027')->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/academic-years/{$mine->id}/activate");

        $this->assertTrue($otherActive->fresh()->is_active);
    }

    public function test_semester_sequence_is_unique_per_academic_year(): void
    {
        $year = AcademicYear::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/academic-years/{$year->id}/semesters", [
                'name' => 'Semester 1',
                'sequence' => 1,
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-12-31',
            ]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/academic-years/{$year->id}/semesters", [
                'name' => 'Semester Ganjil Lagi',
                'sequence' => 1,
                'starts_on' => '2026-07-01',
                'ends_on' => '2026-12-31',
            ])
            ->assertSessionHasErrors('sequence');

        $this->assertSame(1, Semester::where('academic_year_id', $year->id)->count());
    }

    public function test_semester_dates_must_be_ordered(): void
    {
        $year = AcademicYear::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->post("/academic-years/{$year->id}/semesters", [
                'name' => 'Semester 1',
                'sequence' => 1,
                'starts_on' => '2026-12-31',
                'ends_on' => '2026-07-01',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_semester_can_use_the_same_sequence_in_another_year(): void
    {
        $first = AcademicYear::factory()->create(['school_id' => $this->school->id]);
        $second = AcademicYear::factory()->create(['school_id' => $this->school->id]);

        foreach ([$first, $second] as $year) {
            $this->actingInSchool($this->admin, $this->school)
                ->post("/academic-years/{$year->id}/semesters", [
                    'name' => 'Semester 1',
                    'sequence' => 1,
                    'starts_on' => '2026-07-01',
                    'ends_on' => '2026-12-31',
                ])
                ->assertRedirect(route('academic-years.show', $year));
        }

        $this->assertSame(2, Semester::where('school_id', $this->school->id)->count());
    }

    public function test_academic_year_from_another_tenant_is_not_readable(): void
    {
        $other = School::factory()->create();
        $foreignYear = AcademicYear::factory()->create(['school_id' => $other->id]);

        $this->actingInSchool($this->admin, $this->school)
            ->get("/academic-years/{$foreignYear->id}")
            ->assertNotFound();

        $this->actingInSchool($this->admin, $this->school)
            ->post("/academic-years/{$foreignYear->id}/activate")
            ->assertNotFound();

        $this->assertFalse($foreignYear->fresh()->is_active);
    }

    public function test_teacher_can_read_but_not_change_academic_years(): void
    {
        $teacher = $this->makeUserWithRole($this->school, 'teacher');
        $this->refreshPermissionCache();

        $year = AcademicYear::factory()->create(['school_id' => $this->school->id]);

        $this->actingInSchool($teacher, $this->school)->get('/academic-years')->assertOk();

        $this->actingInSchool($teacher, $this->school)
            ->get('/academic-years/create')
            ->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->post('/academic-years', [
                'name' => '2027/2028',
                'starts_on' => '2027-07-01',
                'ends_on' => '2028-06-30',
            ])
            ->assertForbidden();

        $this->actingInSchool($teacher, $this->school)
            ->post("/academic-years/{$year->id}/activate")
            ->assertForbidden();

        $this->assertFalse($year->fresh()->is_active);
    }
}
