<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\StudyGroup;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class StudyGroupPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.academics');
    }

    public function view(User $user, StudyGroup $group): bool
    {
        return $this->owns($group) && $user->can('view.academics');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.academics');
    }

    public function update(User $user, StudyGroup $group): bool
    {
        return $this->owns($group) && $user->can('manage.academics');
    }

    public function delete(User $user, StudyGroup $group): bool
    {
        return $this->owns($group) && $user->can('manage.academics');
    }

    /**
     * Moving students in and out of a group is an administrative action, kept
     * separate from plain editing so it can be granted independently later.
     */
    public function manageMembers(User $user, StudyGroup $group): bool
    {
        return $this->owns($group) && $user->can('manage.academics');
    }
}
