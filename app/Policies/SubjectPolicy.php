<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Subject;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class SubjectPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.academics');
    }

    public function view(User $user, Subject $subject): bool
    {
        return $this->owns($subject) && $user->can('view.academics');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.academics');
    }

    public function update(User $user, Subject $subject): bool
    {
        return $this->owns($subject) && $user->can('manage.academics');
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->owns($subject) && $user->can('manage.academics');
    }
}
