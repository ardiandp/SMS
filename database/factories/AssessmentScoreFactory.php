<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentScore>
 */
class AssessmentScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'assessment_id' => Assessment::factory(),
            'student_id' => Student::factory(),
            'score' => fake()->randomFloat(2, 0, 100),
            'feedback' => null,
            'entered_by' => null,
            'finalized_at' => null,
            'previous_score' => null,
            'corrected_by' => null,
            'corrected_at' => null,
            'correction_count' => 0,
        ];
    }

    public function forAssessment(Assessment $assessment, Student $student, ?float $score = null, ?User $enterer = null): static
    {
        return $this->state(fn (): array => [
            'school_id' => $assessment->school_id,
            'assessment_id' => $assessment->getKey(),
            'student_id' => $student->getKey(),
            'score' => $score ?? fake()->randomFloat(2, 0, 100),
            'entered_by' => $enterer?->getKey(),
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => ['finalized_at' => now()]);
    }

    public function corrected(float $previous, User $actor): static
    {
        return $this->state(fn (): array => [
            'previous_score' => $previous,
            'corrected_by' => $actor->getKey(),
            'corrected_at' => now(),
            'correction_count' => 1,
        ]);
    }
}
