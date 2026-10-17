<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\AcademicEvent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

/**
 * Kalender akademik dibaca semua warga sekolah yang punya dasbor;
 * perubahan hanya untuk pengelola akademik (PRD-05).
 */
class AcademicEventPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.dashboard');
    }

    public function view(User $user, AcademicEvent $event): bool
    {
        return $this->owns($event) && $user->can('view.dashboard');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.academics');
    }

    public function update(User $user, AcademicEvent $event): bool
    {
        return $this->owns($event) && $user->can('manage.academics');
    }

    public function delete(User $user, AcademicEvent $event): bool
    {
        return $this->owns($event) && $user->can('manage.academics');
    }
}
