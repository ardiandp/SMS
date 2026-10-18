<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\SchoolLetterStatus;
use App\Models\SchoolLetter;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

/** Surat sekolah (PRD-05). */
class SchoolLetterPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.dashboard');
    }

    public function view(User $user, SchoolLetter $letter): bool
    {
        return $this->owns($letter) && $user->can('view.dashboard');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.letters');
    }

    public function update(User $user, SchoolLetter $letter): bool
    {
        // Hanya draf yang boleh diubah (PRD-05).
        return $this->owns($letter)
            && $user->can('manage.letters')
            && $letter->status === SchoolLetterStatus::Draft;
    }

    public function issue(User $user, SchoolLetter $letter): bool
    {
        // Terbit hanya dari draf; batalkan hanya surat terbit.
        return $this->owns($letter)
            && $user->can('manage.letters')
            && $letter->status === SchoolLetterStatus::Draft;
    }

    public function cancel(User $user, SchoolLetter $letter): bool
    {
        return $this->owns($letter)
            && $user->can('manage.letters')
            && $letter->status === SchoolLetterStatus::Issued;
    }
}
