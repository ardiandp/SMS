<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\AcademicYear;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class AcademicYearPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.academics');
    }

    public function view(User $user, AcademicYear $year): bool
    {
        return $this->owns($year) && $user->can('view.academics');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.academics');
    }

    public function update(User $user, AcademicYear $year): bool
    {
        return $this->owns($year) && $user->can('manage.academics');
    }

    /**
     * Years are never deleted: semesters, study groups and schedules reference
     * them, and the history must stay intact (AGENTS.md rule 9).
     */
    public function delete(User $user, AcademicYear $year): bool
    {
        return false;
    }
}
