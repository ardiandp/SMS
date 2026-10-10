<?php

namespace Database\Factories;

use App\Enums\FinalGradeStatus;
use App\Models\FinalGrade;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinalGrade>
 */
class FinalGradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'teaching_assignment_id' => TeachingAssignment::factory(),
            'semester_id' => Semester::factory(),
            'numeric_score' => fake()->randomFloat(2, 0, 100),
            'grade_label' => null,
            'calculation_version' => null,
            'calculation_detail_json' => null,
            'status' => FinalGradeStatus::Draft,
            'finalized_by' => null,
            'finalized_at' => null,
            'corrected_by' => null,
            'corrected_at' => null,
        ];
    }

    /**
     * Bind the grade to one student/assignment/semester context so the factory
     * never produces a cross-tenant relation by accident.
     */
    public function forContext(
        Student $student,
        TeachingAssignment $assignment,
        ?Semester $semester = null,
    ): static {
        return $this->state(fn (): array => [
            'school_id' => $assignment->school_id,
            'student_id' => $student->getKey(),
            'teaching_assignment_id' => $assignment->getKey(),
            'semester_id' => $semester?->getKey() ?? $assignment->semester_id,
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'status' => FinalGradeStatus::Finalized,
            'finalized_at' => now(),
        ]);
    }
}
