<?php

namespace App\Policies;

use App\Domain\Attendance\TeacherAssignmentResolver;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class AttendanceRecordPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly TeacherAssignmentResolver $teacherAssignments,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.attendance');
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->owns($record) && $user->can('view.attendance');
    }

    /**
     * Marking follows the session's own rule: assigned teacher, or an
     * administrator.
     */
    public function update(User $user, AttendanceRecord $record): bool
    {
        if (! $this->owns($record)) {
            return false;
        }

        if ($user->can('manage.attendance')) {
            return true;
        }

        return $user->can('view.attendance')
            && $this->teacherAssignments->teaches($user, $record->session->studyGroup);
    }

    /**
     * Correcting a finalized record is stricter: it rewrites an official number,
     * so it needs the dedicated permission on top of tenant ownership.
     */
    public function correct(User $user, AttendanceRecord $record): bool
    {
        return $this->owns($record) && $user->can('correct.attendance');
    }

    /**
     * Bulk CSV export carries student personal data, so it is gated behind its
     * own permission rather than plain read access.
     */
    public function export(User $user): bool
    {
        return $this->inSchool() && $user->can('export.attendance');
    }
}
