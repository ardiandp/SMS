<?php

namespace Database\Factories;

use App\Enums\ReportCardStatus;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCard>
 */
class ReportCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'semester_id' => Semester::factory(),
            'status' => ReportCardStatus::Draft,
            'published_at' => null,
            'published_by' => null,
            'revision_no' => 1,
        ];
    }

    public function forContext(Student $student, AcademicYear $year, ?Semester $semester = null): static
    {
        return $this->state(fn (): array => [
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'academic_year_id' => $year->getKey(),
            'semester_id' => $semester?->getKey() ?? Semester::factory()->forYear($year)->create([
                'school_id' => $year->school_id,
            ])->getKey(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ReportCardStatus::Published,
            'published_at' => now(),
        ]);
    }
}
