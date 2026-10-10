<?php

namespace Database\Factories;

use App\Models\GradeLevel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeLevel>
 */
class GradeLevelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // code is unique per school, and the counter only grows so two grade
        // levels created inside one test never collide.
        static $sequence = 0;

        $sequence++;
        $number = $sequence % 99;

        return [
            'school_id' => School::factory(),
            'code' => $number.'-'.fake()->unique()->lexify('??'),
            'name' => 'Kelas '.$number,
            'education_level' => fake()->randomElement(['SD', 'SMP', 'SMA', 'SMK']),
            'sequence' => $number,
            'is_active' => true,
        ];
    }

    public function level(string $educationLevel, int $sequence): static
    {
        static $suffix = 0;

        $suffix++;

        return $this->state(fn (): array => [
            'code' => $sequence.'-'.strtolower($educationLevel).$suffix,
            'name' => $educationLevel.' Kelas '.$sequence,
            'education_level' => $educationLevel,
            'sequence' => $sequence,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
