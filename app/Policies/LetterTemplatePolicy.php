<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\LetterTemplate;
use App\Models\School;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

/** Template surat sekolah (PRD-05). */
class LetterTemplatePolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.templates');
    }

    public function view(User $user, LetterTemplate $template): bool
    {
        return $this->owns($template) && $user->can('manage.templates');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.templates');
    }

    public function update(User $user, LetterTemplate $template): bool
    {
        return $this->owns($template) && $user->can('manage.templates');
    }

    public function delete(User $user, LetterTemplate $template): bool
    {
        return $this->owns($template) && $user->can('manage.templates');
    }
}