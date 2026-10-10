<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * The same request serves both endpoints, so the ability must follow the route
     * instead of always checking `create`.
     */
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            ? ($this->user()?->can('update', $employee) ?? false)
            : ($this->user()?->can('create', Employee::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();
        $employeeId = $this->route('employee');

        return [
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')
                    ->where('school_id', $schoolId)
                    ->ignore($employeeId),
            ],
            'full_name' => ['required', 'string', 'max:191'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'joined_on' => ['nullable', 'date'],
            'left_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
        ];
    }
}
