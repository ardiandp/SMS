<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\ClassSchedule;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class ClassSchedulePolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.academics');
    }

    public function view(User $user, ClassSchedule $schedule): bool
    {
        return $this->owns($schedule) && $user->can('view.academics');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.schedules');
    }

    public function update(User $user, ClassSchedule $schedule): bool
    {
        return $this->owns($schedule) && $user->can('manage.schedules');
    }

    public function delete(User $user, ClassSchedule $schedule): bool
    {
        return $this->owns($schedule) && $user->can('manage.schedules');
    }
}
