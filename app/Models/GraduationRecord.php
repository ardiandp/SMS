<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\GraduationStatus;
use Database\Factories\GraduationRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-student graduation record for an academic year (PRD-04).
 *
 * The graduation rule is decided by the school; the application only records
 * the declaration through PromotionService::issueGraduation (permission
 * `issue.graduations`), with an audit trail. One row per student/year
 * enforced by a unique index.
 *
 * @property string $status
 */
class GraduationRecord extends Model
{
    /** @use HasFactory<GraduationRecordFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'status',
        'document_number',
        'issued_at',
        'issued_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GraduationStatus::class,
            'issued_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
