<?php

namespace App\Http\Requests;

use App\Models\AssessmentScore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Correcting one finalized score. Requires a reason: the audit trail is only
 * useful if it says why the number changed.
 */
class CorrectAssessmentScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $score = $this->route('assessmentScore');

        return $score instanceof AssessmentScore
            && ($this->user()?->can('correct', $score->assessment) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $score = $this->route('assessmentScore');
        $maxScore = $score instanceof AssessmentScore
            ? (float) ($score->assessment?->max_score ?? 100)
            : 100;

        return [
            'score' => ['required', 'numeric', 'min:0', "max:{$maxScore}"],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
