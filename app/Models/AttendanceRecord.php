<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One student's attendance in one session.
 *
 * The correction trail (previous_status, corrected_by, corrected_at,
 * correction_count) is what makes a post-hoc change auditable, as PRD-03
 * requires.
 *
 * @property AttendanceStatus $status
 * @property string|null $previous_status
 * @property int $correction_count
 */
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'attendance_session_id',
        'student_id',
        'status',
        'notes',
        'marked_by',
        'marked_at',
        'previous_status',
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
            'status' => AttendanceStatus::class,
            'previous_status' => AttendanceStatus::class,
            'marked_at' => 'datetime',
            'corrected_at' => 'datetime',
            'correction_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AttendanceSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
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
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /**
     * @return HasOne<AttendanceExcuse, $this>
     */
    public function excuse(): HasOne
    {
        return $this->hasOne(AttendanceExcuse::class);
    }

    public function wasCorrected(): bool
    {
        return $this->correction_count > 0;
    }
}
