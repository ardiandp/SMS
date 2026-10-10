<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAssignment>
 */
class EmployeeAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'role_title' => fake()->randomElement(['Guru Matematika', 'Wali Kelas', 'Staf TU']),
            'starts_on' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'ends_on' => null,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'school_id' => $employee->school_id,
            'employee_id' => $employee->getKey(),
        ]);
    }
}
