<?php

namespace App\Enums;

enum SchoolStatus: string
{
    case Active = 'active';

    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }
}
