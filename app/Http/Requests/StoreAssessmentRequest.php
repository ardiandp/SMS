<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Assessment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');

        return $assessment instanceof Assessment
            ? ($this->user()?->can('update', $assessment) ?? false)
            : ($this->user()?->can('create', Assessment::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return [
            'teaching_assignment_id' => [
                'required', 'integer',
                Rule::exists('teaching_assignments', 'id')->where('school_id', $schoolId),
            ],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('assessment_categories', 'id')->where('school_id', $schoolId),
            ],
            'title' => ['required', 'string', 'max:191'],
            'assessment_date' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assessmentData(): array
    {
        return [
            'teaching_assignment_id' => $this->integer('teaching_assignment_id'),
            'category_id' => $this->filled('category_id') ? $this->integer('category_id') : null,
            'title' => $this->string('title')->toString(),
            'assessment_date' => $this->date('assessment_date')->toDateString(),
            'max_score' => (float) $this->input('max_score'),
            'weight' => $this->filled('weight') ? (float) $this->input('weight') : null,
        ];
    }
}
