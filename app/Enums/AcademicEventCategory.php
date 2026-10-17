<?php

namespace App\Enums;

/**
 * Kategori kegiatan kalender akademik (PRD-05).
 *
 * Disimpan sebagai string di kolom category agar daftarnya dapat bertambah
 * tanpa migrasi; enum ini adalah kosakata yang dikenal aplikasi.
 */
enum AcademicEventCategory: string
{
    case Academic = 'academic';
    case Holiday = 'holiday';
    case Activity = 'activity';
    case Meeting = 'meeting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Akademik',
            self::Holiday => 'Libur',
            self::Activity => 'Kegiatan',
            self::Meeting => 'Rapat',
            self::Other => 'Lainnya',
        };
    }
}
