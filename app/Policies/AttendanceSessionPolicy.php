<?php

namespace App\Policies;

use App\Domain\Attendance\TeacherAssignmentResolver;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class AttendanceSessionPolicy
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

    public function view(User $user, AttendanceSession $session): bool
    {
        return $this->owns($session) && $user->can('view.attendance');
    }

    /**
     * Opening a session is an administrative act; teachers cannot create them.
     */
    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.attendance');
    }

    /**
     * A teacher may manage a session only for a group they are assigned to teach.
     * Users holding `manage.attendance` bypass the assignment check.
     */
    public function update(User $user, AttendanceSession $session): bool
    {
        if (! $this->owns($session)) {
            return false;
        }

        if ($user->can('manage.attendance')) {
            return true;
        }

        return $user->can('view.attendance')
            && $this->teacherAssignments->teaches($user, $session->studyGroup);
    }

    public function finalize(User $user, AttendanceSession $session): bool
    {
        return $this->owns($session) && $user->can('manage.attendance');
    }

    /**
     * Reopening a finalized session is reserved for administrators.
     */
    public function reopen(User $user, AttendanceSession $session): bool
    {
        return $this->owns($session) && $user->can('manage.attendance');
    }

    public function delete(User $user, AttendanceSession $session): bool
    {
        return false;
    }
}
