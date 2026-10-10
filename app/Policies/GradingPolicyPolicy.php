<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\GradingPolicy;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

/**
 * Grading policies are school-level configuration: only administrators may
 * create or activate them (teachers supply raw scores, never the rules).
 */
class GradingPolicyPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && ($user->can('view.grades') || $user->can('manage.grading-policies'));
    }

    public function view(User $user, GradingPolicy $policy): bool
    {
        return $this->owns($policy) && ($user->can('view.grades') || $user->can('manage.grading-policies'));
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.grading-policies');
    }

    /**
     * Activating a version changes which rules produce future grades —
     * an act reserved for policy managers.
     */
    public function activate(User $user, GradingPolicy $policy): bool
    {
        return $this->owns($policy) && $user->can('manage.grading-policies');
    }

    public function delete(User $user, GradingPolicy $policy): bool
    {
        // Versions are immutable history; they are archived, never deleted.
        return false;
    }
}
