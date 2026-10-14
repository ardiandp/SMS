<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class PromotionDecisionPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.promotions');
    }

    /**
     * Recording who moves up, stays back, or graduates changes a student's
     * academic path — an administrative act.
     */
    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.promotions');
    }
}
