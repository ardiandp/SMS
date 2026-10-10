<?php

namespace App\Enums;

/**
 * Status of a single student placement inside a study group.
 *
 * `active` is the only value covered by the one-active-placement-per-student
 * database constraint, so closing a row (rather than deleting it) is what
 * makes history readable and lets a student move to the next year.
 */
enum PlacementStatus: string
{
    case Active = 'active';

    case Moved = 'moved';

    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Moved => 'Pindah',
            self::Closed => 'Selesai',
        };
    }
}
