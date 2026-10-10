<?php

namespace Database\Factories;

use App\Enums\PlacementStatus;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyGroupMember>
 */
class StudyGroupMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'starts_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'ends_on' => null,
            'status' => PlacementStatus::Active,
        ];
    }

    /**
     * @param  Closure(Student): Student  $studentFactory
     */
    public function forStudentIn(StudyGroup $group, $studentFactory = null): static
    {
        return $this->state(function () use ($group, $studentFactory): array {
            $student = is_callable($studentFactory)
                ? $studentFactory()
                : Student::factory()->create(['school_id' => $group->school_id]);

            return [
                'school_id' => $group->school_id,
                'study_group_id' => $group->getKey(),
                'student_id' => $student->getKey(),
            ];
        });
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => PlacementStatus::Closed,
            'ends_on' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ]);
    }

    public function moved(): static
    {
        return $this->state(fn (): array => [
            'status' => PlacementStatus::Moved,
            'ends_on' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ]);
    }
}
