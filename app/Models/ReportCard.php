<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\ReportCardStatus;
use Database\Factories\ReportCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One published (or draft) report card revision for a student/semester.
 *
 * A revision creates a new row with revision_no + 1 instead of overwriting the
 * previous one, so the publication history stays readable (PRD-04).
 *
 * @property int $revision_no
 * @property ReportCardStatus $status
 */
class ReportCard extends Model
{
    /** @use HasFactory<ReportCardFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'semester_id',
        'status',
        'published_at',
        'published_by',
        'revision_no',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportCardStatus::class,
            'published_at' => 'datetime',
            'revision_no' => 'integer',
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
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<ReportCardItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReportCardItem::class);
    }
}
