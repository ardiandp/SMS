<?php

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\School;
use App\Models\Semester;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'teaching_assignment_id' => TeachingAssignment::factory(),
            'semester_id' => Semester::factory(),
            'category_id' => AssessmentCategory::factory(),
            'title' => fake()->words(3, true),
            'assessment_date' => fake()->date(),
            'max_score' => 100,
            'weight' => null,
            'status' => AssessmentStatus::Draft,
            'created_by' => null,
        ];
    }

    /**
     * Bind the assessment to one teaching assignment and its tenant so the
     * factory never produces a cross-tenant relation by accident.
     */
    public function forAssignment(TeachingAssignment $assignment, ?AssessmentCategory $category = null): static
    {
        return $this->state(fn (): array => [
            'school_id' => $assignment->school_id,
            'teaching_assignment_id' => $assignment->getKey(),
            'semester_id' => $assignment->semester_id,
            'category_id' => $category?->getKey() ?? AssessmentCategory::factory()->create([
                'school_id' => $assignment->school_id,
            ])->getKey(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => AssessmentStatus::Published]);
    }
}
