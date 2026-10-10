<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\ClassScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weekly recurring lesson slot for one teaching assignment.
 *
 * day_of_week uses ISO-8601 numbering (1 = Monday .. 7 = Sunday) so the column
 * can be CHECK-constrained and does not depend on a locale.
 *
 * @property int $day_of_week
 */
class ClassSchedule extends Model
{
    /** @use HasFactory<ClassScheduleFactory> */
    use HasFactory, ScopedBySchool;

    /**
     * ISO-8601 day numbering, Monday first.
     *
     * @var list<string>
     */
    public const DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    protected $fillable = [
        'school_id',
        'teaching_assignment_id',
        'day_of_week',
        'starts_at',
        'ends_at',
        'effective_from',
        'effective_until',
        'room_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<TeachingAssignment, $this>
     */
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function dayLabel(): string
    {
        return self::DAYS[$this->day_of_week] ?? '-';
    }

    /**
     * True when this slot's time range overlaps another slot on the same day.
     * Half-open intervals: a lesson ending at 10:00 does not clash with one
     * starting at 10:00.
     */
    public function overlaps(ClassSchedule $other): bool
    {
        if ($this->day_of_week !== $other->day_of_week) {
            return false;
        }

        $startsAt = (string) $this->starts_at;
        $endsAt = (string) $this->ends_at;
        $otherStartsAt = (string) $other->starts_at;
        $otherEndsAt = (string) $other->ends_at;

        return $startsAt < $otherEndsAt && $otherStartsAt < $endsAt;
    }
}
