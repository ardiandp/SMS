<?php

namespace App\Enums;

/**
 * Status surat sekolah (PRD-05).
 *
 * Alur: draft -> issued. Surat terbit adalah dokumen resmi dengan snapshot
 * isi dan tidak dapat diubah; pembatalan tidak menghapus melainkan menandai
 * cancelled agar nomor surat tetap terlacak.
 */
enum SchoolLetterStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Issued => 'Terbit',
            self::Cancelled => 'Batal',
        };
    }
}
