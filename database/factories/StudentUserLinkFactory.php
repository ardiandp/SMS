<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Student;
use App\Models\StudentUserLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentUserLink>
 */
class StudentUserLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function linking(Student $student, User $user): static
    {
        return $this->state(fn (): array => [
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }
}
