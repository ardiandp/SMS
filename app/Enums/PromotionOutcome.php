<?php

namespace App\Enums;

/**
 * Outcome of an official end-of-year promotion deliberation (PRD-04).
 *
 * The application deliberately does NOT compute this from grades: the school
 * records its own approved decision, per student, with an audit trail.
 */
enum PromotionOutcome: string
{
    case Promoted = 'promoted';

    case Repeated = 'repeated';

    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Promoted => 'Naik Kelas',
            self::Repeated => 'Tinggal Kelas',
            self::Graduated => 'Lulus',
        };
    }
}
