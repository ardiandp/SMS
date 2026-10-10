<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
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
            'employee_number' => 'G'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'employment_status' => fake()->randomElement(['permanent', 'contract', 'probation']),
            'joined_on' => fake()->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
            'left_on' => null,
        ];
    }
}
