<?php

namespace Database\Factories;

use App\Models\AcademicEvent;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicEvent>
 */
class AcademicEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 month', '+2 months');

        return [
            'school_id' => School::factory(),
            'academic_year_id' => null,
            'title' => fake('id_ID')->sentence(3),
            'description' => fake('id_ID')->sentence(8),
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => (clone $startsAt)->modify('+2 hours')->format('Y-m-d H:i:s'),
            'category' => 'academic',
            'audience_json' => null,
            'created_by' => null,
        ];
    }
}
