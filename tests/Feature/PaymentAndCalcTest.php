<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentAndCalcTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_payment_confirm_dispatches_calc_job(): void
    {
        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'job-1'], 202),
        ]);

        $package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::query()->create([
            'name' => 'Pay User',
            'email' => 'pay@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $payments = app(PaymentService::class);
        $started = $payments->start($user, 100, PaymentProvider::Manual, ['package_id' => $package->id]);
        $this->assertSame('pending', $started['transaction']->status);

        $confirmed = $payments->confirm($started['transaction']);
        $this->assertSame('completed', $confirmed->status);

        $this->assertDatabaseHas('calculation_jobs', [
            'job_type' => 'place-member',
            'status' => 'completed',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/internal/jobs/place-member'));
    }

    public function test_nowpayments_stub_initiate_and_ipn_confirms_payment(): void
    {
        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
        ]);

        config([
            'payments.nowpayments.api_key' => null,
            'payments.nowpayments.ipn_secret' => null,
        ]);

        $user = User::query()->create([
            'name' => 'NP User',
            'email' => 'np@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $started = app(PaymentService::class)->start($user, 100, PaymentProvider::NowPayments);
        $tx = $started['transaction'];
        $this->assertSame(PaymentProvider::NowPayments, $tx->provider);
        $this->assertSame('pending', $tx->status);

        $this->postJson(route('webhooks.payments.handle', 'nowpayments'), [
            'payment_id' => 999,
            'payment_status' => 'finished',
            'order_id' => $tx->provider_ref,
            'price_amount' => 100,
            'price_currency' => 'usd',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('completed', $tx->fresh()->status);
    }

    public function test_nowpayments_payout_stub_completes_withdrawal(): void
    {
        config([
            'payments.default_payout' => 'nowpayments',
            'payments.nowpayments.api_key' => null,
        ]);

        $user = User::query()->create([
            'name' => 'WD User',
            'email' => 'wd@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'wallet_balance' => '100.00',
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
            'status' => WithdrawalStatus::Pending,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-np@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.complete', $withdrawal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $withdrawal->fresh();
        $this->assertSame(WithdrawalStatus::Completed, $fresh->status);
        $this->assertSame('nowpayments', $fresh->payout_provider);
        $this->assertNotEmpty($fresh->payout_ref);
    }

    public function test_live_nowpayments_payout_validates_and_verifies(): void
    {
        Http::fake([
            '*/auth' => Http::response(['token' => 'jwt-test'], 200),
            '*/payout/validate-address' => Http::response(['status' => 'OK'], 200),
            '*/payout/*/verify' => Http::response(['status' => 'OK'], 200),
            '*/payout' => Http::response([
                'id' => '5000000713',
                'withdrawals' => [
                    ['id' => '5000000000', 'status' => 'CREATING'],
                ],
            ], 200),
        ]);

        config([
            'payments.default_payout' => 'nowpayments',
            'payments.nowpayments.api_key' => 'test-key',
            'payments.nowpayments.email' => 'np@test.com',
            'payments.nowpayments.password' => 'secret',
            'payments.nowpayments.two_fa_secret' => 'JBSWY3DPEHPK3PXP',
            'payments.nowpayments.validate_payout_address' => true,
        ]);

        $user = User::query()->create([
            'name' => 'WD Live',
            'email' => 'wd-live@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'wallet_balance' => '100.00',
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
            'status' => WithdrawalStatus::Pending,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Live',
            'email' => 'admin-live-np@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.complete', $withdrawal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $withdrawal->fresh();
        $this->assertSame(WithdrawalStatus::Processing, $fresh->status);
        $this->assertSame('5000000000', $fresh->payout_ref);
        $this->assertArrayHasKey('verify_response', $fresh->meta ?? []);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payout/validate-address'));
        Http::assertSent(fn ($request) => str_ends_with(rtrim($request->url(), '/'), '/payout/5000000713/verify'));
    }

    public function test_payout_webhook_is_csrf_exempt(): void
    {
        config([
            'payments.nowpayments.api_key' => null,
            'payments.nowpayments.ipn_secret' => null,
        ]);

        $user = User::query()->create([
            'name' => 'IPN WD',
            'email' => 'ipn-wd@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'wallet_balance' => '50.00',
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
            'status' => WithdrawalStatus::Processing,
            'payout_provider' => 'nowpayments',
            'payout_ref' => '5000000000',
        ]);

        $this->postJson(route('webhooks.payouts.handle', 'nowpayments'), [
            'id' => '5000000000',
            'status' => 'FINISHED',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(WithdrawalStatus::Completed, $withdrawal->fresh()->status);
    }
}
