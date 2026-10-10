<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'nis' => 'S'.fake()->unique()->numerify('######'),
            'nisn' => fake()->unique()->numerify('##########'),
            'full_name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'status' => 'active',
        ];
    }
}
