<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentGuardian>
 */
class StudentGuardianFactory extends Factory
{
    /**
     * The tenant invariant (pivot.school_id must match both sides) is enforced
     * by StudentService::linkGuardian in production, so tests must pass all
     * three ids explicitly through linkedTo() rather than relying on defaults.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'relationship' => 'parent',
            'is_primary' => false,
            'can_pickup' => null,
            'receives_notifications' => true,
        ];
    }

    public function linkedTo(Student $student, Guardian $guardian): static
    {
        return $this->state(fn (): array => [
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'guardian_id' => $guardian->getKey(),
        ]);
    }
}
