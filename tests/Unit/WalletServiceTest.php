<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_increases_balance(): void
    {
        $user = User::factory()->customer()->create(['wallet_balance' => '10.00']);
        $service = app(WalletService::class);

        $updated = $service->credit($user, '15.50');

        $this->assertSame('25.50', $service->balance($updated));
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => '15.50',
            'balance_after' => '25.50',
        ]);
    }

    public function test_debit_decreases_balance(): void
    {
        $user = User::factory()->customer()->create(['wallet_balance' => '50.00']);
        $service = app(WalletService::class);

        $updated = $service->debit($user, '20.00');

        $this->assertSame('30.00', $service->balance($updated));
    }

    public function test_debit_rejects_insufficient_funds(): void
    {
        $user = User::factory()->customer()->create(['wallet_balance' => '5.00']);
        $service = app(WalletService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient wallet balance.');

        $service->debit($user, '10.00');
    }

    public function test_credit_rejects_non_positive_amount(): void
    {
        $user = User::factory()->customer()->create(['wallet_balance' => '5.00']);
        $service = app(WalletService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->credit($user, 0);
    }
}
