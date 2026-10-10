<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StudentDocument>
 */
class StudentDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'document_type' => fake()->randomElement(['birth_certificate', 'report_card', 'photo']),
            'storage_path' => 'students/'.Str::uuid()->toString().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 204_800),
            'uploaded_by' => User::factory(),
        ];
    }

    public function forStudent(Student $student, User $uploader): static
    {
        return $this->state(fn (): array => [
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'uploaded_by' => $uploader->getKey(),
        ]);
    }
}
