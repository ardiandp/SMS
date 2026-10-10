<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\GradeLevel;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class GradeLevelPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.academics');
    }

    public function view(User $user, GradeLevel $gradeLevel): bool
    {
        return $this->owns($gradeLevel) && $user->can('view.academics');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.academics');
    }

    public function update(User $user, GradeLevel $gradeLevel): bool
    {
        return $this->owns($gradeLevel) && $user->can('manage.academics');
    }

    public function delete(User $user, GradeLevel $gradeLevel): bool
    {
        return $this->owns($gradeLevel) && $user->can('manage.academics');
    }
}
