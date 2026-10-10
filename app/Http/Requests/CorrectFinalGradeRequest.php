<?php

namespace App\Http\Requests;

use App\Models\FinalGrade;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Correcting one finalized final grade. Requires a reason: the previous value
 * lives only in the audit log, so the log must say why it changed.
 */
class CorrectFinalGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grade = $this->route('finalGrade');

        return $grade instanceof FinalGrade
            && ($this->user()?->can('correct', $grade) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numeric_score' => ['required', 'numeric', 'between:0,100'],
            'grade_label' => ['nullable', 'string', 'max:5'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
