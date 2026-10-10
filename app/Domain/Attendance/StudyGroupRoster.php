<?php

namespace App\Domain\Attendance;

use App\Enums\PlacementStatus;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use Illuminate\Support\Collection;

/**
 * Resolves which students belong to a study group on a given date.
 *
 * Why not just "status = active": a student who moves to another class
 * mid-year keeps an active placement in the new group, but on the date the
 * session happened they belonged to the old one. Taking the date range into
 * account means historical attendance stays attached to the right class.
 *
 * Placements are closed with an `ends_on` date when a student moves, so the
 * interval check is: starts_on <= date AND (ends_on IS NULL OR ends_on >= date).
 */
class StudyGroupRoster
{
    /**
     * @return Collection<int, StudyGroupMember>
     */
    public function membersOn(StudyGroup $group, string $date): Collection
    {
        return $group->members()
            ->where('starts_on', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('ends_on')
                    ->orWhere('ends_on', '>=', $date);
            })
            ->whereIn('status', [
                PlacementStatus::Active->value,
                PlacementStatus::Closed->value,
                PlacementStatus::Moved->value,
            ])
            ->get();
    }

    /**
     * Student ids in the group on a given date.
     *
     * @return list<int>
     */
    public function studentIdsOn(StudyGroup $group, string $date): array
    {
        return $this->membersOn($group, $date)
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsOn(StudyGroup $group, string $date): Collection
    {
        $ids = $this->studentIdsOn($group, $date);

        if ($ids === []) {
            return collect();
        }

        return Student::query()
            ->whereIn('id', $ids)
            ->orderBy('full_name')
            ->get();
    }

    public function isMemberOn(StudyGroup $group, Student $student, string $date): bool
    {
        return in_array((int) $student->getKey(), $this->studentIdsOn($group, $date), true);
    }
}
