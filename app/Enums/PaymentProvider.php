<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Manual = 'manual';
    case NowPayments = 'nowpayments';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::NowPayments => 'NOWPayments',
        };
    }
}
