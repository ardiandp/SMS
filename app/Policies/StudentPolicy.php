<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.students');
    }

    public function view(User $user, Student $student): bool
    {
        return $this->owns($student) && $user->can('view.students');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.students');
    }

    public function update(User $user, Student $student): bool
    {
        return $this->owns($student) && $user->can('manage.students');
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->owns($student) && $user->can('manage.students');
    }

    private function inSchool(): bool
    {
        return $this->currentSchool->isResolved();
    }

    private function owns(Student $student): bool
    {
        return $this->inSchool()
            && $student->school_id === $this->currentSchool->school()->getKey();
    }
}
