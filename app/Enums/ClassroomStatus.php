<?php

namespace App\Enums;

enum ClassroomStatus: string
{
    case Active = 'active';

    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Tersedia',
            self::Unavailable => 'Tidak Tersedia',
        };
    }
}
