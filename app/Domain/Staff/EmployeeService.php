<?php

namespace App\Domain\Staff;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($data, $actor): Employee {
            $employee = new Employee($data);
            $employee->assignToCurrentSchool();
            $employee->save();

            $this->audit->record(
                action: 'employee.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $employee,
                metadata: ['employee_id' => $employee->getKey()],
            );

            return $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor): Employee {
            $employee->fill($data);
            $employee->save();

            $this->audit->record(
                action: 'employee.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $employee,
                metadata: ['employee_id' => $employee->getKey()],
            );

            return $employee;
        });
    }

    /**
     * Deactivate an employee instead of deleting them: employment history and any
     * teaching assignments must stay referencable. Records the previous status in
     * the audit metadata without storing personal data.
     */
    public function deactivate(Employee $employee, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $actor): Employee {
            $previous = $employee->employment_status;

            $employee->forceFill([
                'employment_status' => EmploymentStatus::Former,
                'left_on' => $employee->left_on ?? today(),
            ])->save();

            $this->audit->record(
                action: 'employee.deactivated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $employee,
                metadata: [
                    'employee_id' => $employee->getKey(),
                    'from' => $previous->value,
                    'to' => EmploymentStatus::Former->value,
                ],
            );

            return $employee;
        });
    }
}
