<?php

namespace Database\Factories;

use App\Models\LetterTemplate;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterTemplate>
 */
class LetterTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'SK-'.fake()->unique()->bothify('###'),
            'name' => 'Surat '.fake('id_ID')->words(2, true),
            'body_template' => "Yang bertanda tangan di bawah ini menerangkan bahwa {{nama}}.\n\nDemikian surat ini dibuat.",
            'version' => 1,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
