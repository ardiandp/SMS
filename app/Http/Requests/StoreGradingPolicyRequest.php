<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\GradingPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a grading policy version.
 *
 * The weights map is keyed by assessment category id and is checked
 * structurally by GradingPolicyService (sum 100, known categories, descending
 * bands); the request validates the surrounding shape.
 */
class StoreGradingPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GradingPolicy::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return [
            'name' => ['required', 'string', 'max:100'],
            'academic_year_id' => [
                'required', 'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'config' => ['required', 'array'],
            'config.weights' => ['required', 'array', 'min:1'],
            'config.weights.*' => ['required', 'numeric', 'gt:0', 'max:100'],
            'config.grade_bands' => ['required', 'array', 'min:1'],
            'config.grade_bands.*.label' => ['required', 'string', 'max:10'],
            'config.grade_bands.*.min' => ['required', 'numeric', 'between:0,100'],
            'config.rounding' => ['nullable', 'integer', 'between:0,4'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function policyConfig(): array
    {
        $config = (array) $this->input('config', []);

        return [
            'weights' => array_map('floatval', (array) ($config['weights'] ?? [])),
            'grade_bands' => array_values((array) ($config['grade_bands'] ?? [])),
            'rounding' => (int) ($config['rounding'] ?? 2),
        ];
    }
}
