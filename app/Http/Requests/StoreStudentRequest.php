<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Student::class) ?? false;
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
            'gender' => ['nullable', Rule::enum(\App\Enums\Gender::class)],
            'status' => ['required', Rule::enum(\App\Enums\StudentStatus::class)],
        ];
    }
}
