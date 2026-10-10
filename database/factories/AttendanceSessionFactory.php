<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_group_id' => StudyGroup::factory(),
            'session_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'session_type' => 'daily',
            'teaching_assignment_id' => null,
            'status' => 'open',
            'created_by' => User::factory(),
        ];
    }

    /**
     * Bind the session to a study group, tenant and creator at once.
     */
    public function forGroup(StudyGroup $group, User $creator, string $date): static
    {
        return $this->state(fn (): array => [
            'school_id' => $group->school_id,
            'study_group_id' => $group->getKey(),
            'session_date' => $date,
            'created_by' => $creator->getKey(),
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => ['status' => 'finalized']);
    }
}
