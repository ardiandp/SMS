<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Permanent = 'permanent';

    case Contract = 'contract';

    case Probation = 'probation';

    case Former = 'former';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'PNS/Tetap',
            self::Contract => 'Kontrak',
            self::Probation => 'Probation',
            self::Former => 'Alumni/Pensiun',
        };
    }
}
