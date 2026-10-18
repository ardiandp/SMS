<?php

namespace Database\Factories;

use App\Enums\SchoolLetterStatus;
use App\Models\School;
use App\Models\SchoolLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolLetter>
 */
class SchoolLetterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'template_id' => null,
            'letter_number' => fake()->unique()->bothify('000.###/SM/??/####'),
            'subject' => fake('id_ID')->sentence(3),
            'body_snapshot' => fake('id_ID')->paragraph(),
            'status' => SchoolLetterStatus::Draft,
            'issued_at' => null,
            'created_by' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => SchoolLetterStatus::Issued,
            'issued_at' => now(),
        ]);
    }
}
