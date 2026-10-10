<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.employees');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->owns($employee) && $user->can('view.employees');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.employees');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->owns($employee) && $user->can('manage.employees');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->owns($employee) && $user->can('manage.employees');
    }

    private function inSchool(): bool
    {
        return $this->currentSchool->isResolved();
    }

    private function owns(Employee $employee): bool
    {
        return $this->inSchool()
            && $employee->school_id === $this->currentSchool->school()->getKey();
    }
}
