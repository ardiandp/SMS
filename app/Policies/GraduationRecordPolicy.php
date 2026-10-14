<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class GraduationRecordPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.promotions');
    }

    /**
     * Issuing the graduation document is the strongest act in this domain;
     * it gets its own permission, separate from recording promotion decisions.
     */
    public function issue(User $user): bool
    {
        return $this->inSchool() && $user->can('issue.graduations');
    }
}
