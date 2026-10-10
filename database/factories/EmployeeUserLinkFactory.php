<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeUserLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeUserLink>
 */
class EmployeeUserLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function linking(Employee $employee, User $user): static
    {
        return $this->state(fn (): array => [
            'school_id' => $employee->school_id,
            'employee_id' => $employee->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }
}
