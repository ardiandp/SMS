<?php

namespace Database\Factories;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => fake('id_ID')->sentence(4),
            'body' => '<p>'.fake('id_ID')->sentence(10).'</p>',
            'status' => AnnouncementStatus::Draft,
            'published_at' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => AnnouncementStatus::Published,
            'published_at' => now(),
        ]);
    }
}
