<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\AssessmentStatus;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One gradable event (quiz, assignment, exam) owned by a teaching assignment.
 *
 * Raw scores live in assessment_scores; the computed result lives in
 * final_grades. Keeping them apart lets a policy change be re-run without
 * losing what the teacher originally entered.
 *
 * @property string $title
 * @property AssessmentStatus $status
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'teaching_assignment_id',
        'semester_id',
        'category_id',
        'title',
        'assessment_date',
        'max_score',
        'weight',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'status' => AssessmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<TeachingAssignment, $this>
     */
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsTo<AssessmentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssessmentScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }
}
