<?php

namespace App\Services\Payments;

class PaymentEnvironment
{
    /**
     * Stub gateways (complete without live NOWPayments) are allowed only in local/testing
     * unless PAYMENT_ALLOW_STUB is set explicitly.
     */
    public static function allowsStub(): bool
    {
        $explicit = config('payments.allow_stub');

        if ($explicit === true || $explicit === 1 || $explicit === '1' || $explicit === 'true') {
            return true;
        }

        if ($explicit === false || $explicit === 0 || $explicit === '0' || $explicit === 'false') {
            return false;
        }

        return app()->environment(['local', 'testing']);
    }
}
