<?php

namespace App\Support;

class UsdtWalletAddress
{
    public const NETWORK_TRC20 = 'trc20';
    public const NETWORK_BEP20 = 'bep20';

    /**
     * USDT on BNB Smart Chain (BEP-20) uses 0x + 40 hex.
     */
    public static function isEvm(string $address): bool
    {
        return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', trim($address));
    }

    public static function isBep20(string $address): bool
    {
        return self::isEvm($address);
    }

    /**
     * USDT on Tron (TRC-20) uses a Base58 address starting with T (34 chars).
     */
    public static function isTrc20(string $address): bool
    {
        return (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', trim($address));
    }

    public static function isSupported(string $address): bool
    {
        return self::isTrc20($address) || self::isBep20($address);
    }

    public static function network(string $address): ?string
    {
        if (self::isTrc20($address)) {
            return self::NETWORK_TRC20;
        }

        if (self::isBep20($address)) {
            return self::NETWORK_BEP20;
        }

        return null;
    }

    /**
     * NOWPayments ticker for this address: usdttrc20 or usdtbsc.
     */
    public static function nowPaymentsCurrency(string $address): ?string
    {
        return match (self::network($address)) {
            self::NETWORK_TRC20 => strtolower((string) config('payments.nowpayments.payout_currency_trc20', 'usdttrc20')),
            self::NETWORK_BEP20 => strtolower((string) config('payments.nowpayments.payout_currency_bep20', 'usdtbsc')),
            default => null,
        };
    }

    public static function label(?string $network): string
    {
        return match ($network) {
            self::NETWORK_TRC20 => 'USDT TRC-20',
            self::NETWORK_BEP20 => 'USDT BEP-20',
            default => 'USDT',
        };
    }
}
