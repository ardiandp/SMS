<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\PromotionOutcome;
use Database\Factories\PromotionDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-student promotion decision for an academic year (PRD-04).
 *
 * The application never computes the decision from grades: the school records
 * its own approved outcome (naik kelas / tinggal kelas / lulus) through
 * PromotionService, which audits every write. One row per student/year; a
 * re-deliberation updates that row and leaves the previous value in the audit
 * log.
 *
 * @property string $decision
 */
class PromotionDecision extends Model
{
    /** @use HasFactory<PromotionDecisionFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'from_grade_level_id',
        'to_grade_level_id',
        'decision',
        'notes',
        'decided_by',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => PromotionOutcome::class,
            'decided_at' => 'datetime',
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
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function fromGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'from_grade_level_id');
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function toGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'to_grade_level_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
