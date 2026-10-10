<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\AttendanceExcuse;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class AttendanceExcusePolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.attendance');
    }

    public function view(User $user, AttendanceExcuse $excuse): bool
    {
        return $this->owns($excuse) && $user->can('view.attendance');
    }

    /**
     * Any user who can see the attendance may submit a reason; the record must
     * belong to the active tenant.
     */
    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('view.attendance');
    }

    /**
     * Evidence files are the sensitive part: only administrators may download
     * them, even when the excuse itself is visible to a teacher.
     */
    public function download(User $user, AttendanceExcuse $excuse): bool
    {
        return $this->owns($excuse) && $user->can('manage.attendance');
    }

    public function review(User $user, AttendanceExcuse $excuse): bool
    {
        return $this->owns($excuse) && $user->can('manage.attendance');
    }
}
