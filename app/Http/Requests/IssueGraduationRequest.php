<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\GraduationRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Issuing a graduation record (PRD-04).
 */
class IssueGraduationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('issue', GraduationRecord::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return [
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'academic_year_id' => [
                'required', 'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'document_number' => ['required', 'string', 'max:100'],
        ];
    }
}
