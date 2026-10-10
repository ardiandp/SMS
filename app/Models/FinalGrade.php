<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\FinalGradeStatus;
use Database\Factories\FinalGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A computed final grade for one student, one assignment, one semester.
 *
 * calculation_detail_json stores the per-category breakdown and
 * calculation_version names the grading policy version that produced the
 * number, so a published grade can always be explained without re-running the
 * calculation (PRD-04 acceptance criteria).
 *
 * @property string|null $numeric_score
 * @property array<string, mixed>|null $calculation_detail_json
 * @property FinalGradeStatus $status
 */
class FinalGrade extends Model
{
    /** @use HasFactory<FinalGradeFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'teaching_assignment_id',
        'semester_id',
        'numeric_score',
        'grade_label',
        'calculation_version',
        'calculation_detail_json',
        'status',
        'finalized_by',
        'finalized_at',
        'corrected_by',
        'corrected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numeric_score' => 'decimal:2',
            'calculation_detail_json' => 'array',
            'status' => FinalGradeStatus::class,
            'finalized_at' => 'datetime',
            'corrected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
     * @return BelongsTo<User, $this>
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
