<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Semester '.fake()->numberBetween(1, 2),
            'starts_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'ends_on' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'sequence' => fake()->numberBetween(1, 2),
        ];
    }

    public function forYear(AcademicYear $year): static
    {
        return $this->state(fn (): array => [
            'school_id' => $year->school_id,
            'academic_year_id' => $year->getKey(),
        ]);
    }

    public function numbered(int $sequence): static
    {
        return $this->state(fn (): array => [
            'sequence' => $sequence,
            'name' => 'Semester '.$sequence,
        ]);
    }
}
