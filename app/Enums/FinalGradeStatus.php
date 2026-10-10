<?php

namespace App\Enums;

/**
 * Lifecycle of a computed final grade (PRD-04).
 *
 * `finalized` grades may only change through a correction that records actor,
 * timestamp and previous value — never through a normal save.
 */
enum FinalGradeStatus: string
{
    case Draft = 'draft';

    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Finalized => 'Difinalisasi',
        };
    }
}
