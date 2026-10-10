<?php

namespace App\Enums;

/**
 * Lifecycle of an attendance session.
 *
 * `open` sessions can still be edited freely. `finalized` sessions are locked:
 * changing them is a correction and must go through the correction path, which
 * records the previous status, the actor and the time.
 */
enum AttendanceSessionStatus: string
{
    case Open = 'open';

    case Finalized = 'finalized';

    public function isLocked(): bool
    {
        return $this === self::Finalized;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Berlangsung',
            self::Finalized => 'Selesai',
        };
    }
}
