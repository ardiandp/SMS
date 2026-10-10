<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ReportCard;
use App\Models\ReportCardItem;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCardItem>
 */
class ReportCardItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'report_card_id' => ReportCard::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Employee::factory(),
            'final_grade_snapshot_json' => [
                'numeric_score' => null,
                'grade_label' => null,
                'calculation_version' => null,
            ],
            'teacher_note' => null,
        ];
    }

    /**
     * @param  array{numeric_score?: string|null, grade_label?: string|null, calculation_version?: string|null}  $snapshot
     */
    public function forCard(ReportCard $card, Subject $subject, array $snapshot = []): static
    {
        return $this->state(fn (): array => [
            'school_id' => $card->school_id,
            'report_card_id' => $card->getKey(),
            'subject_id' => $subject->getKey(),
            'teacher_id' => null,
            'final_grade_snapshot_json' => $snapshot + [
                'numeric_score' => null,
                'grade_label' => null,
                'calculation_version' => null,
            ],
        ]);
    }
}
