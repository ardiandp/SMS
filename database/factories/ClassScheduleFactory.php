<?php

namespace Database\Factories;

use App\Models\ClassSchedule;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSchedule>
 */
class ClassScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teaching_assignment_id' => TeachingAssignment::factory(),
            'day_of_week' => fake()->numberBetween(1, 6),
            'starts_at' => '07:00:00',
            'ends_at' => '08:30:00',
            'effective_from' => null,
            'effective_until' => null,
            'room_label' => null,
        ];
    }

    public function forAssignment(TeachingAssignment $assignment): static
    {
        return $this->state(fn (): array => [
            'school_id' => $assignment->school_id,
            'teaching_assignment_id' => $assignment->getKey(),
        ]);
    }

    public function slot(int $dayOfWeek, string $startsAt, string $endsAt, ?string $room = null): static
    {
        return $this->state(fn (): array => [
            'day_of_week' => $dayOfWeek,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'room_label' => $room,
        ]);
    }
}
