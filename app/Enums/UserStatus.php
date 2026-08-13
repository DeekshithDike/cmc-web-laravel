<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case PowerId = 'power_id';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Expired => 'Expired',
            self::PowerId => 'Power ID',
        };
    }
}
