<?php

namespace Database\Factories;

use App\Enums\GradingPolicyStatus;
use App\Models\AcademicYear;
use App\Models\GradingPolicy;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingPolicy>
 */
class GradingPolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Kebijakan Penilaian '.fake()->unique()->numberBetween(1, 999),
            'config_json' => [
                'weights' => [],
                'grade_bands' => [
                    ['label' => 'A', 'min' => 90],
                    ['label' => 'B', 'min' => 80],
                    ['label' => 'C', 'min' => 70],
                    ['label' => 'D', 'min' => 60],
                ],
                'rounding' => 2,
            ],
            'version' => 1,
            'status' => GradingPolicyStatus::Draft,
        ];
    }

    public function forYear(AcademicYear $year): static
    {
        return $this->state(fn (): array => [
            'school_id' => $year->school_id,
            'academic_year_id' => $year->getKey(),
        ]);
    }

    /**
     * @param  array<string, float>  $weights  category id => percentage
     * @param  list<array{label: string, min: float}>  $gradeBands
     */
    public function withConfig(array $weights, array $gradeBands = [], int $rounding = 2): static
    {
        return $this->state(fn (): array => [
            'config_json' => [
                'weights' => $weights,
                'grade_bands' => $gradeBands === [] ? [
                    ['label' => 'A', 'min' => 90],
                    ['label' => 'B', 'min' => 80],
                    ['label' => 'C', 'min' => 70],
                    ['label' => 'D', 'min' => 60],
                ] : $gradeBands,
                'rounding' => $rounding,
            ],
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => GradingPolicyStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => GradingPolicyStatus::Archived]);
    }
}
