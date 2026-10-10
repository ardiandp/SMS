<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Active = 'active';

    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Finished => 'Selesai',
        };
    }
}
