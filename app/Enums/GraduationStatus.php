<?php

namespace App\Enums;

/**
 * Lifecycle of a graduation record (PRD-04).
 *
 * The graduation rule itself is decided by the school; the application only
 * records who was declared graduated, when, and under which document number.
 */
enum GraduationStatus: string
{
    case Pending = 'pending';

    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Issued => 'Diterbitkan',
        };
    }
}
