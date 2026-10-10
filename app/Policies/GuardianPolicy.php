<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy
{
    public function __construct(private readonly CurrentSchool $currentSchool)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.guardians');
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $this->owns($guardian) && $user->can('manage.guardians');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.guardians');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->owns($guardian) && $user->can('manage.guardians');
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $this->owns($guardian) && $user->can('manage.guardians');
    }

    private function inSchool(): bool
    {
        return $this->currentSchool->isResolved();
    }

    private function owns(Guardian $guardian): bool
    {
        return $this->inSchool()
            && $guardian->school_id === $this->currentSchool->school()->getKey();
    }
}
