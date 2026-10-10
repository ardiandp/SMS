<?php

namespace Database\Factories;

use App\Enums\StudyGroupStatus;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyGroup>
 */
class StudyGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'classroom_id' => null,
            'homeroom_employee_id' => null,
            'name' => fake()->unique()->bothify('??#'),
            'capacity' => 32,
            'status' => StudyGroupStatus::Active,
        ];
    }

    /**
     * Bind the group to one tenant, academic year and grade level at once so the
     * factory never produces a cross-tenant relation by accident.
     */
    public function forContext(
        AcademicYear $year,
        GradeLevel $gradeLevel,
        string $name,
    ): static {
        return $this->state(fn (): array => [
            'school_id' => $year->school_id,
            'academic_year_id' => $year->getKey(),
            'grade_level_id' => $gradeLevel->getKey(),
            'name' => $name,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => StudyGroupStatus::Archived]);
    }
}
