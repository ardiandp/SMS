<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\StudentDocument;
use App\Models\User;

class StudentDocumentPolicy
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.students');
    }

    public function download(User $user, StudentDocument $document): bool
    {
        return $this->owns($document) && $user->can('view.students');
    }

    public function upload(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.documents');
    }

    public function delete(User $user, StudentDocument $document): bool
    {
        return $this->owns($document) && $user->can('manage.documents');
    }

    private function inSchool(): bool
    {
        return $this->currentSchool->isResolved();
    }

    private function owns(StudentDocument $document): bool
    {
        return $this->inSchool()
            && $document->school_id === $this->currentSchool->school()->getKey();
    }
}
