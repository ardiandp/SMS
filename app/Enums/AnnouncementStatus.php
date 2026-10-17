<?php

namespace App\Enums;

/**
 * Status pengumuman sekolah (PRD-05).
 *
 * Alur: draft -> published -> archived. Tidak ada transisi mundur selain
 * archived sebagai status terminal arsip.
 */
enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
            self::Archived => 'Arsip',
        };
    }
}
