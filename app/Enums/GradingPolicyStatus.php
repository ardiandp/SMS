<?php

namespace App\Enums;

/**
 * Lifecycle of a grading policy version (PRD-04).
 *
 * A policy is created as a draft and only affects calculations once it is
 * activated; activating a version archives the previously active one so there
 * is never ambiguity about which rules produced a given final grade.
 */
enum GradingPolicyStatus: string
{
    case Draft = 'draft';

    case Active = 'active';

    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Aktif',
            self::Archived => 'Diarsipkan',
        };
    }
}
