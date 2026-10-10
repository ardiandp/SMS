<?php

namespace App\Enums;

/**
 * Lifecycle of an assessment.
 *
 * A `published` assessment stops accepting edits: its scores have been used to
 * produce a final grade, so changing one is a correction, not a normal save.
 */
enum AssessmentStatus: string
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