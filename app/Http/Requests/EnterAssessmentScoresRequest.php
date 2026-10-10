<?php

namespace App\Http\Requests;

use App\Domain\Assessment\AssessmentScoreService;
use App\Models\Assessment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Entering draft scores for one assessment.
 *
 * The `scores` map is keyed by student id. Each id must belong to the
 * assignment's roster on the assessment date, so a crafted id from another
 * class cannot be scored through this endpoint.
 */
class EnterAssessmentScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');

        return $assessment instanceof Assessment
            && ($this->user()?->can('update', $assessment) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0'],
            'scores.*.feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Reject any student id that is not on the roster, and any score above the
     * assessment's maximum.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $assessment = $this->route('assessment');

            if (! $assessment instanceof Assessment) {
                return;
            }

            $maxScore = (float) $assessment->max_score;

            foreach ((array) $this->input('scores', []) as $studentId => $entry) {
                $score = is_array($entry) ? ($entry['score'] ?? null) : null;

                if ($score !== null && (float) $score > $maxScore) {
                    $validator->errors()->add(
                        "scores.$studentId.score",
                        'Nilai melebihi skor maksimum asesmen.',
                    );
                }
            }

            $ids = array_map('intval', array_keys((array) $this->input('scores', [])));

            $eligible = app(AssessmentScoreService::class)->eligibleStudentIds($assessment);

            foreach (array_diff($ids, $eligible) as $invalid) {
                $validator->errors()->add("scores.$invalid", 'Siswa tidak terdaftar pada rombel penugasan ini.');
            }
        });
    }

    /**
     * @return array<int, array{score: float|null, feedback: string|null}>
     */
    public function scoresByStudentId(): array
    {
        $scores = [];

        foreach ((array) $this->input('scores', []) as $studentId => $entry) {
            $scores[(int) $studentId] = [
                'score' => isset($entry['score']) && $entry['score'] !== '' ? (float) $entry['score'] : null,
                'feedback' => $entry['feedback'] ?? null,
            ];
        }

        return $scores;
    }
}
