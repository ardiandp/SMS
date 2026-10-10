<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingAssignment>
 */
class TeachingAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_group_id' => StudyGroup::factory(),
            'subject_id' => Subject::factory(),
            'employee_id' => Employee::factory(),
            'semester_id' => null,
            'status' => AssignmentStatus::Active,
        ];
    }

    /**
     * Build a fully consistent assignment: every referenced record belongs to the
     * same tenant as the study group.
     */
    public function forGroup(
        StudyGroup $group,
        Subject $subject,
        Employee $employee,
        ?Semester $semester = null,
    ): static {
        return $this->state(fn (): array => [
            'school_id' => $group->school_id,
            'study_group_id' => $group->getKey(),
            'subject_id' => $subject->getKey(),
            'employee_id' => $employee->getKey(),
            'semester_id' => $semester?->getKey(),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (): array => ['status' => AssignmentStatus::Finished]);
    }
}
