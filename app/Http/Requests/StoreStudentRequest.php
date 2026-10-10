<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /**
     * The same request serves both endpoints, so the ability must follow the route
     * instead of always checking `create`.
     */
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            ? ($this->user()?->can('update', $student) ?? false)
            : ($this->user()?->can('create', Student::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();
        $studentId = $this->route('student');

        return [
            'nis' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'nis')
                    ->where('school_id', $schoolId)
                    ->ignore($studentId),
            ],
            'nisn' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:191'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'status' => ['required', Rule::enum(StudentStatus::class)],
        ];
    }
}
