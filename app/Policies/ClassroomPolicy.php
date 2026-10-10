<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class ClassroomPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.academics');
    }

    public function view(User $user, Classroom $classroom): bool
    {
        return $this->owns($classroom) && $user->can('view.academics');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.academics');
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $this->owns($classroom) && $user->can('manage.academics');
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $this->owns($classroom) && $user->can('manage.academics');
    }
}
