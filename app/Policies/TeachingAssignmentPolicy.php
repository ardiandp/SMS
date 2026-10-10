<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class TeachingAssignmentPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.academics');
    }

    public function view(User $user, TeachingAssignment $assignment): bool
    {
        return $this->owns($assignment) && $user->can('view.academics');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.academics');
    }

    public function update(User $user, TeachingAssignment $assignment): bool
    {
        return $this->owns($assignment) && $user->can('manage.academics');
    }

    public function delete(User $user, TeachingAssignment $assignment): bool
    {
        return $this->owns($assignment) && $user->can('manage.academics');
    }

    /**
     * A teacher may view their own assignments even without the academic
     * permission; anything else still requires the tenant check to pass.
     */
    public function viewOwn(User $user, TeachingAssignment $assignment): bool
    {
        return $this->owns($assignment);
    }
}
