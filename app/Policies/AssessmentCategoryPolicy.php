<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\AssessmentCategory;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

/**
 * Categories feed the grading policy weights, so managing them is a
 * configuration act reserved for holders of `manage.grading-policies`.
 */
class AssessmentCategoryPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.grades');
    }

    public function view(User $user, AssessmentCategory $category): bool
    {
        return $this->owns($category) && $user->can('view.grades');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.grading-policies');
    }

    public function update(User $user, AssessmentCategory $category): bool
    {
        return $this->owns($category) && $user->can('manage.grading-policies');
    }

    public function delete(User $user, AssessmentCategory $category): bool
    {
        return $this->owns($category) && $user->can('manage.grading-policies');
    }
}
