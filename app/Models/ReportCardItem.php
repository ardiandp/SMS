<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\ReportCardItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subject line on a report card revision.
 *
 * final_grade_snapshot_json holds the numeric score, label and policy version
 * at publication time. The row is never recomputed, so a later policy change
 * cannot rewrite what the school already published.
 *
 * @property array<string, mixed> $final_grade_snapshot_json
 */
class ReportCardItem extends Model
{
    /** @use HasFactory<ReportCardItemFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'report_card_id',
        'subject_id',
        'teacher_id',
        'final_grade_snapshot_json',
        'teacher_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'final_grade_snapshot_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ReportCard, $this>
     */
    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }
}
