<?php

namespace App\Models;

use App\Domain\Tenancy\CurrentSchool;
use App\Domain\Tenancy\ScopedBySchool;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifikasi in-app milik pengguna (PRD-05).
 *
 * Memakai skema baku notifications milik Laravel, tetapi ikut
 * ScopedBySchool sehingga setiap baris terikat sekolah aktif saat dibuat
 * dan tidak pernah terbaca lintas tenant.
 */
class Notification extends DatabaseNotification
{
    use ScopedBySchool;

    protected static function booted(): void
    {
        static::creating(function (self $notification): void {
            // school_id diisi dari konteks tenant, bukan dari input request,
            // mengikuti pola assignToCurrentSchool() pada model tenant lain.
            // Tanpa konteks sekolah, kolom non-nullable gagal insert — sengaja
            // keras agar tidak pernah ada notifikasi tanpa pemilik sekolah.
            if ($notification->school_id === null) {
                $notification->school_id = app(CurrentSchool::class)->school()?->getKey();
            }
        });
    }
}
