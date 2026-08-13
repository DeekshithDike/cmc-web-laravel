<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\Income\DailyIncomeService;
use App\Services\Membership\MembershipService;
use App\Services\Payments\PaymentService;
use App\Services\Payouts\NowPaymentsPayoutGateway;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MoneyPathAndIncomeTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private User $admin;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['ok' => true], 202)]);

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-mp@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'Root',
            'email' => 'root-mp@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'wallet_balance' => '100.00',
        ]);

        BinaryTree::query()->create(['users_id' => $this->root->id]);
    }

    public function test_payout_stub_is_rejected_when_stubs_disabled(): void
    {
        config([
            'payments.allow_stub' => false,
            'payments.nowpayments.api_key' => null,
            'payments.nowpayments.email' => null,
            'payments.nowpayments.password' => null,
        ]);

        $wd = Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
            'status' => WithdrawalStatus::Pending,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NOWPayments payout is not configured');
        app(NowPaymentsPayoutGateway::class)->send($wd);
    }

    public function test_nowpayments_without_key_does_not_activate_when_stubs_disabled(): void
    {
        config([
            'payments.default_receive' => 'nowpayments',
            'payments.allow_stub' => false,
            'payments.nowpayments.api_key' => null,
        ]);

        $this->assertTrue(app(PaymentService::class)->requiresLiveCheckout());

        $this->expectException(RuntimeException::class);
        app(PaymentService::class)->start($this->root, 100);
    }

    public function test_processing_withdrawal_cannot_be_declined(): void
    {
        $wd = Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
            'status' => WithdrawalStatus::Processing,
            'payout_provider' => 'nowpayments',
            'payout_ref' => 'wd-in-flight',
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.withdrawals.index', 'processing'))
            ->post(route('admin.withdrawals.decline', $wd))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(WithdrawalStatus::Processing, $wd->fresh()->status);
        $this->assertSame('100.00', number_format((float) $this->root->fresh()->wallet_balance, 2, '.', ''));
    }

    public function test_ipn_underpayment_does_not_activate_member(): void
    {
        config([
            'payments.default_receive' => 'nowpayments',
            'payments.nowpayments.api_key' => null,
            'payments.nowpayments.ipn_secret' => null,
        ]);

        $user = app(MembershipService::class)->createActiveMember([
            'name' => 'Unpaid',
            'email' => 'unpaid-mp@test.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ], true);

        $started = app(PaymentService::class)->start($user, 100, PaymentProvider::NowPayments);
        $this->postJson(route('webhooks.payments.handle', 'nowpayments'), [
            'payment_status' => 'finished',
            'order_id' => $started['transaction']->provider_ref,
            'price_amount' => 10,
        ])->assertOk();

        $this->assertSame('failed', $started['transaction']->fresh()->status);
        $this->assertFalse((bool) $user->fresh()->payment_status);
    }

    public function test_invite_register_ignores_spoofed_sponsor(): void
    {
        $thief = User::query()->create([
            'name' => 'Thief',
            'email' => 'thief@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'left',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->assertRedirectedToCredentials(
            $this->post(route('customer.register.save'), [
                'name' => 'Spoofed',
                'email' => 'spoofed@test.com',
                'sponsor_id' => $thief->id,
                'parent_id' => $this->root->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $child = User::query()->where('email', 'spoofed@test.com')->firstOrFail();
        $this->assertSame($this->root->id, $child->sponsor_id);
        $this->assertSame(0, ReferralIncome::query()->where('user_id', $thief->id)->count());
        $this->assertSame(1, ReferralIncome::query()->where('user_id', $this->root->id)->where('from_user_id', $child->id)->count());
    }

    public function test_credentials_are_one_time_and_not_flashed(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Creds',
            'email' => 'creds@test.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ]);

        $location = $this->assertRedirectedToCredentials($response);
        $response->assertSessionMissing('success');

        $page = $this->get($location);
        $page->assertOk()->assertSee('Login ID', false);
        $this->get($location)->assertNotFound();
    }

    public function test_daily_income_pays_binary_and_records_referral_on_activation(): void
    {
        app(MembershipService::class)->createActiveMember([
            'name' => 'Left Leg',
            'email' => 'left-leg@test.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ]);
        app(MembershipService::class)->createActiveMember([
            'name' => 'Right Leg',
            'email' => 'right-leg@test.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
            'package_id' => $this->package->id,
        ]);

        $this->assertEquals('120.00', number_format((float) $this->root->fresh()->wallet_balance, 2, '.', ''));
        $this->assertSame(2, ReferralIncome::query()->where('user_id', $this->root->id)->count());

        $result = app(DailyIncomeService::class)->run(now()->toDateString());
        $this->assertGreaterThanOrEqual(1, $result['processed']);

        $rootRow = PaymentDetail::query()->where('user_id', $this->root->id)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $rootRow->roi_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float) $rootRow->binary_amount, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $rootRow->referral_amount, 2, '.', ''));
        $this->assertEquals('131.00', number_format((float) $this->root->fresh()->wallet_balance, 2, '.', ''));

        $this->actingAs($this->root)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('100.00', false)
            ->assertSee('20.00', false);
    }

    public function test_wallet_writes_ledger_rows(): void
    {
        app(WalletService::class)->credit($this->root, '5.00', 'test_topup');

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->root->id,
            'type' => 'credit',
            'amount' => '5.00',
            'reason' => 'test_topup',
        ]);
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $this->root->id)->count());
    }
}
