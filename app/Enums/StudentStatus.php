<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';

    case Inactive = 'inactive';

    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Withdrawn => 'Keluar',
        };
    }
}
