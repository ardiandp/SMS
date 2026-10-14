<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\PromotionOutcome;
use App\Models\PromotionDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording a promotion decision (PRD-04).
 *
 * The decision is the school's own deliberation outcome; this request only
 * validates that every referenced record exists within the current tenant.
 */
class RecordPromotionDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PromotionDecision::class) ?? false;
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
            'decision' => ['required', Rule::enum(PromotionOutcome::class)],
            'from_grade_level_id' => ['nullable', 'integer',
                Rule::exists('grade_levels', 'id')->where('school_id', $schoolId),
            ],
            'to_grade_level_id' => ['nullable', 'integer',
                Rule::exists('grade_levels', 'id')->where('school_id', $schoolId),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
