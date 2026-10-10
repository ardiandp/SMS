<?php

namespace Database\Factories;

use App\Enums\ClassroomStatus;
use App\Models\Classroom;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Ruang '.fake()->unique()->numberBetween(100, 999),
            'building' => fake()->randomElement(['Gedung A', 'Gedung B', 'Gedung C']),
            'capacity' => fake()->numberBetween(24, 40),
            'status' => ClassroomStatus::Active,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => ['status' => ClassroomStatus::Unavailable]);
    }
}
