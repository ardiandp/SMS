<?php

namespace App\Enums;

/**
 * Lifecycle of a report card revision (PRD-04).
 *
 * A revision never mutates a published row: it creates a new revision with a
 * fresh snapshot, keeping the earlier publication history readable.
 */
enum ReportCardStatus: string
{
    case Draft = 'draft';

    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Diterbitkan',
        };
    }
}
