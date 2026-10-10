<?php

namespace App\Domain\Academics;

use App\Enums\PlacementStatus;
use App\Models\Student;
use App\Models\StudyGroupMember;
use Illuminate\Support\Collection;

/**
 * Helpers around the generated `active_student_id` column.
 *
 * The database guarantees a student has at most one row with status 'active'.
 * Closing a row (never deleting it) is what makes the student eligible for a new
 * placement and keeps the history queryable.
 */
class StudyGroupMemberService
{
    /**
     * @return Collection<int, StudyGroupMember>
     */
    public static function activePlacementsFor(Student $student): Collection
    {
        return StudyGroupMember::query()
            ->where('student_id', $student->getKey())
            ->where('status', PlacementStatus::Active->value)
            ->get();
    }

    /**
     * Close a placement by writing the end date and dropping it out of the
     * active set; the row itself stays for reporting.
     */
    public static function closePlacement(StudyGroupMember $placement, string $endsOn): StudyGroupMember
    {
        $placement->forceFill([
            'status' => PlacementStatus::Closed,
            'ends_on' => $placement->ends_on ?? $endsOn,
        ])->save();

        return $placement;
    }

    /**
     * Mark a placement as "moved" instead of a plain close, so a report can tell
     * the difference between finishing a year and transferring classes.
     */
    public static function movePlacement(StudyGroupMember $placement, string $endsOn): StudyGroupMember
    {
        $placement->forceFill([
            'status' => PlacementStatus::Moved,
            'ends_on' => $placement->ends_on ?? $endsOn,
        ])->save();

        return $placement;
    }
}
