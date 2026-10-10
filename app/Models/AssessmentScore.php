<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\AssessmentScoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's raw score on one assessment.
 *
 * The correction trail (previous_score, corrected_by, corrected_at,
 * correction_count) mirrors attendance_records: changing a finalized score
 * must keep the actor, the time and the old value (PRD-04).
 *
 * @property string|null $previous_score
 * @property int $correction_count
 */
class AssessmentScore extends Model
{
    /** @use HasFactory<AssessmentScoreFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'assessment_id',
        'student_id',
        'score',
        'feedback',
        'entered_by',
        'finalized_at',
        'previous_score',
        'corrected_by',
        'corrected_at',
        'correction_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'finalized_at' => 'datetime',
            'previous_score' => 'decimal:2',
            'corrected_at' => 'datetime',
            'correction_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enterer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function wasCorrected(): bool
    {
        return $this->correction_count > 0;
    }
}
