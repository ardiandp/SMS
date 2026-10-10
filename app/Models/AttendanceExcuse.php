<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\ExcuseStatus;
use Database\Factories\AttendanceExcuseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reason (and optional evidence file) for a sick or permitted absence.
 *
 * The document lives on the private disk and is only reachable through a
 * controller plus policy — never through a public URL.
 *
 * @property ExcuseStatus $status
 */
class AttendanceExcuse extends Model
{
    /** @use HasFactory<AttendanceExcuseFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'attendance_record_id',
        'student_id',
        'reason',
        'document_path',
        'mime_type',
        'size_bytes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => ExcuseStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AttendanceRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasDocument(): bool
    {
        return $this->document_path !== null && $this->document_path !== '';
    }
}
