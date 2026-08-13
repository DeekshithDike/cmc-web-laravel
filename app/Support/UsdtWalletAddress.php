<?php

namespace App\Support;

class UsdtWalletAddress
{
    /**
     * USDT on Ethereum (ERC-20) and BNB Smart Chain (BEP-20) share the 0x + 40 hex format.
     */
    public static function isEvm(string $address): bool
    {
        return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', trim($address));
    }
}
