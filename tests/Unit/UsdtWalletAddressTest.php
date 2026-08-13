<?php

namespace Tests\Unit;

use App\Support\UsdtWalletAddress;
use PHPUnit\Framework\TestCase;

class UsdtWalletAddressTest extends TestCase
{
    public function test_accepts_erc20_and_bep20_hex_addresses(): void
    {
        $this->assertTrue(UsdtWalletAddress::isEvm('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertTrue(UsdtWalletAddress::isEvm('0xABCDEF1234567890ABCDEF1234567890ABCDEF12'));
    }

    public function test_rejects_trc20_and_short_or_non_hex(): void
    {
        $this->assertFalse(UsdtWalletAddress::isEvm('TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS'));
        $this->assertFalse(UsdtWalletAddress::isEvm('0xabc1234567'));
        $this->assertFalse(UsdtWalletAddress::isEvm('0xzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'));
        $this->assertFalse(UsdtWalletAddress::isEvm(''));
    }
}
