<?php

namespace Tests\Unit;

use App\Support\UsdtWalletAddress;
use Tests\TestCase;

class UsdtWalletAddressTest extends TestCase
{
    public function test_accepts_bep20_hex_addresses(): void
    {
        $this->assertTrue(UsdtWalletAddress::isBep20('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertTrue(UsdtWalletAddress::isEvm('0xABCDEF1234567890ABCDEF1234567890ABCDEF12'));
        $this->assertTrue(UsdtWalletAddress::isSupported('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertSame(UsdtWalletAddress::NETWORK_BEP20, UsdtWalletAddress::network('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertSame('usdtbsc', UsdtWalletAddress::nowPaymentsCurrency('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertSame('USDT BEP-20', UsdtWalletAddress::label(UsdtWalletAddress::NETWORK_BEP20));
    }

    public function test_detects_but_does_not_accept_trc20_for_new_withdrawals(): void
    {
        $address = 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS';
        $this->assertTrue(UsdtWalletAddress::isTrc20($address));
        $this->assertFalse(UsdtWalletAddress::isSupported($address));
        $this->assertSame(UsdtWalletAddress::NETWORK_TRC20, UsdtWalletAddress::network($address));
        $this->assertSame('usdttrc20', UsdtWalletAddress::nowPaymentsCurrency($address));
        $this->assertSame('USDT TRC-20', UsdtWalletAddress::label(UsdtWalletAddress::NETWORK_TRC20));
    }

    public function test_rejects_short_or_non_hex(): void
    {
        $this->assertFalse(UsdtWalletAddress::isSupported('0xabc1234567'));
        $this->assertFalse(UsdtWalletAddress::isSupported('0xzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'));
        $this->assertFalse(UsdtWalletAddress::isSupported(''));
        $this->assertFalse(UsdtWalletAddress::isTrc20('0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertNull(UsdtWalletAddress::nowPaymentsCurrency('not-a-wallet'));
    }
}
