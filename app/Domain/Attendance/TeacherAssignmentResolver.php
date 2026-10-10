<?php

namespace App\Domain\Attendance;

use App\Models\EmployeeUserLink;
use App\Models\StudyGroup;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves whether a user is a teacher assigned to a study group.
 *
 * PRD-03 requires that a teacher may only manage sessions for groups they
 * actually teach. That link cannot live in the schema (a user may teach in
 * several groups), so it is resolved here from employee_user_links →
 * teaching_assignments, and every caller must consult it.
 */
class TeacherAssignmentResolver
{
    /**
     * Whether the user has a teaching assignment (active) in the group.
     */
    public function teaches(User $user, StudyGroup $group): bool
    {
        return $this->teachingAssignments($user, $group)->isNotEmpty();
    }

    /**
     * @return Collection<int, TeachingAssignment>
     */
    public function teachingAssignments(User $user, StudyGroup $group)
    {
        $employeeIds = EmployeeUserLink::query()
            ->where('user_id', $user->getKey())
            ->where('school_id', $group->school_id)
            ->pluck('employee_id');

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return TeachingAssignment::query()
            ->where('study_group_id', $group->getKey())
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Employee ids the user is linked to within a school.
     *
     * @return list<int>
     */
    public function employeeIds(User $user, int $schoolId): array
    {
        return EmployeeUserLink::query()
            ->where('user_id', $user->getKey())
            ->where('school_id', $schoolId)
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
