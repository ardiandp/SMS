<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentStatusHistory>
 */
class StudentStatusHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'from_status' => null,
            'to_status' => StudentStatus::Active->value,
            'reason' => null,
            'changed_by' => User::factory(),
        ];
    }

    public function forStudent(Student $student, User $changer): static
    {
        return $this->state(fn (): array => [
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'changed_by' => $changer->getKey(),
        ]);
    }
}
