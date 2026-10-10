<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\PromotionDecision;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionDecision>
 */
class PromotionDecisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'from_grade_level_id' => null,
            'to_grade_level_id' => null,
            'decision' => fake()->randomElement(['promoted', 'repeated', 'graduated']),
            'notes' => null,
            'decided_by' => null,
            'decided_at' => now(),
        ];
    }
}
