<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\AttendanceSessionStatus;
use Database\Factories\AttendanceSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One day's attendance for one study group.
 *
 * The school decided attendance is daily, not per subject slot, so a student has
 * exactly one record per session and records are never duplicated.
 *
 * @property Carbon $session_date
 * @property AttendanceSessionStatus $status
 */
class AttendanceSession extends Model
{
    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'study_group_id',
        'session_date',
        'session_type',
        'teaching_assignment_id',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'status' => AttendanceSessionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<StudyGroup, $this>
     */
    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    /**
     * @return BelongsTo<TeachingAssignment, $this>
     */
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }
}
