<?php

namespace App\Enums;

/**
 * Attendance statuses agreed with the school (PRD-03).
 *
 * Kept as a fixed enum on purpose: the school confirmed these four values, and
 * report totals are defined in terms of them. `present` is the default because
 * the normal flow is "mark everyone present, then change the exceptions".
 */
enum AttendanceStatus: string
{
    case Present = 'present';

    case Sick = 'sick';

    case Permitted = 'permitted';

    case Absent = 'absent';

    /**
     * Statuses that mean "not in class but not absent without reason".
     * Used by the attendance percentage calculation.
     *
     * @return list<self>
     */
    public static function excused(): array
    {
        return [self::Sick, self::Permitted];
    }

    /**
     * Statuses a teacher may attach an excuse to.
     *
     * @return list<self>
     */
    public static function requireExcuse(): array
    {
        return [self::Sick, self::Permitted];
    }

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Sick => 'Sakit',
            self::Permitted => 'Izin',
            self::Absent => 'Alpa',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
