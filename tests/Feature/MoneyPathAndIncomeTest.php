<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\PaymentTransaction;
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
use Illuminate\Support\Carbon;
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

        Carbon::setTestNow('2026-08-14 12:00:00');

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'left',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->post(route('customer.register.save'), [
            'name' => 'Unpaid',
            'email' => 'unpaid-mp@test.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ])->assertRedirect();

        $started = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->postJson(route('webhooks.payments.handle', 'nowpayments'), [
            'payment_status' => 'finished',
            'order_id' => $started->meta['order_id'] ?? $started->provider_ref,
            'price_amount' => 10,
        ])->assertOk();

        $this->assertSame('failed', $started->fresh()->status);
        $this->assertNull(User::query()->where('email', 'unpaid-mp@test.com')->first());
        $this->assertNull(BinaryTree::query()->where('users_id', $this->root->id)->value('left_user_id'));
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

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Spoofed',
                'email' => 'spoofed@test.com',
                'sponsor_id' => $thief->id,
                'parent_id' => $this->root->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $this->assertNull(User::query()->where('email', 'spoofed@test.com')->first());

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

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
        $page->assertOk()->assertSee('Customer ID', false);
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

        $this->assertEquals('100.00', number_format((float) $this->root->fresh()->wallet_balance, 2, '.', ''));
        $this->assertSame(2, ReferralIncome::query()->where('user_id', $this->root->id)->count());
        $this->assertEquals('200.00', number_format((float) ReferralIncome::query()->where('user_id', $this->root->id)->sum('amount'), 2, '.', ''));

        $this->actingAs($this->root)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Today Referral', false)
            ->assertSee('$20.00', false)
            ->assertDontSee('$200.00', false);

        $result = app(DailyIncomeService::class)->run(now()->toDateString());
        $this->assertGreaterThanOrEqual(1, $result['processed']);

        $rootRow = PaymentDetail::query()->where('user_id', $this->root->id)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $rootRow->roi_amount, 2, '.', ''));
        $this->assertSame('5.00', number_format((float) $rootRow->binary_amount, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $rootRow->referral_amount, 2, '.', ''));
        $this->assertEquals('126.00', number_format((float) $this->root->fresh()->wallet_balance, 2, '.', ''));

        $this->actingAs($this->root)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('100.00', false)
            ->assertSee('20.00', false);
    }

    public function test_daily_binary_pay_is_capped_at_active_package_amount(): void
    {
        $asOf = now()->toDateString();

        BinaryTreeLeft::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '5000.00',
            'business_date' => $asOf,
        ]);
        BinaryTreeRight::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '5000.00',
            'business_date' => $asOf,
        ]);

        $before = (float) $this->root->fresh()->wallet_balance;
        app(DailyIncomeService::class)->run($asOf);

        $row = PaymentDetail::query()->where('user_id', $this->root->id)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $row->roi_amount, 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $row->binary_amount, 2, '.', ''));
        $this->assertEquals(
            number_format($before + 101.00, 2, '.', ''),
            number_format((float) $this->root->fresh()->wallet_balance, 2, '.', '')
        );

        $carry = CarryForward::query()->where('user_id', $this->root->id)->whereDate('as_of', $asOf)->firstOrFail();
        $this->assertSame('0.00', number_format((float) $carry->left_carry, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $carry->right_carry, 2, '.', ''));
    }

    public function test_daily_binary_pay_is_capped_at_activated_package_when_five_percent_is_larger(): void
    {
        $elite = Package::query()->create([
            'name' => 'Elite',
            'amount' => '2000.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $this->root->update(['package_id' => $elite->id]);

        $asOf = now()->toDateString();
        BinaryTreeLeft::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '50000.00',
            'business_date' => $asOf,
        ]);
        BinaryTreeRight::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '50000.00',
            'business_date' => $asOf,
        ]);

        $before = (float) $this->root->fresh()->wallet_balance;
        app(DailyIncomeService::class)->run($asOf);

        $row = PaymentDetail::query()->where('user_id', $this->root->id)->firstOrFail();
        $this->assertSame('20.00', number_format((float) $row->roi_amount, 2, '.', ''));
        $this->assertSame('2000.00', number_format((float) $row->binary_amount, 2, '.', ''));
        $this->assertEquals(
            number_format($before + 2020.00, 2, '.', ''),
            number_format((float) $this->root->fresh()->wallet_balance, 2, '.', '')
        );
    }

    public function test_income_daily_command_defaults_to_yesterday_and_skips_duplicates(): void
    {
        $this->artisan('income:daily')
            ->expectsOutputToContain('2026-08-13')
            ->assertSuccessful();

        $this->artisan('income:daily')
            ->expectsOutputToContain('already calculated')
            ->assertSuccessful();

        $this->actingAs($this->admin)->post(route('admin.income.daily.run'))
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->actingAs($this->admin)->get(route('admin.income.daily'))
            ->assertOk()
            ->assertSee('13 Aug 2026', false)
            ->assertSee('Already calculated', false);

        $this->assertSame(1, DailyIncomeRun::query()->whereDate('as_of', '2026-08-13')->count());
    }

    public function test_roi_is_not_paid_on_saturday_or_sunday(): void
    {
        $before = number_format((float) $this->root->wallet_balance, 2, '.', '');

        foreach (['2026-08-15', '2026-08-16'] as $asOf) {
            app(DailyIncomeService::class)->run($asOf);
            $this->assertSame(
                0,
                PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->count()
            );
            $this->assertSame(
                0,
                WalletTransaction::query()->where('user_id', $this->root->id)->where('reason', 'daily_roi')->count()
            );
        }

        $this->assertEquals($before, number_format((float) $this->root->fresh()->wallet_balance, 2, '.', ''));
    }

    public function test_roi_is_paid_on_monday(): void
    {
        $asOf = '2026-08-17';
        app(DailyIncomeService::class)->run($asOf);

        $row = PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $row->roi_amount, 2, '.', ''));
    }

    public function test_roi_is_paid_on_friday(): void
    {
        $asOf = '2026-08-14';
        app(DailyIncomeService::class)->run($asOf);

        $row = PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $row->roi_amount, 2, '.', ''));
    }

    public function test_sunday_skips_roi_but_still_pays_binary_and_referral(): void
    {
        $asOf = '2026-08-16';

        BinaryTreeLeft::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '100.00',
            'business_date' => $asOf,
        ]);
        BinaryTreeRight::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '100.00',
            'business_date' => $asOf,
        ]);
        ReferralIncome::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '100.00',
            'earned_on' => $asOf,
        ]);

        $before = (float) $this->root->fresh()->wallet_balance;
        app(DailyIncomeService::class)->run($asOf);

        $row = PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->firstOrFail();
        $this->assertSame('0.00', number_format((float) $row->roi_amount, 2, '.', ''));
        $this->assertSame('5.00', number_format((float) $row->binary_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float) $row->referral_amount, 2, '.', ''));
        $this->assertEquals(
            number_format($before + 15.00, 2, '.', ''),
            number_format((float) $this->root->fresh()->wallet_balance, 2, '.', '')
        );
    }

    public function test_missing_roi_backfill_does_not_repay_referral(): void
    {
        $asOf = '2026-08-17';
        ReferralIncome::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '200.00',
            'earned_on' => $asOf,
        ]);

        app(DailyIncomeService::class)->run($asOf);

        $row = PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $row->roi_amount, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $row->referral_amount, 2, '.', ''));
        $before = number_format((float) $this->root->fresh()->wallet_balance, 2, '.', '');

        $row->update(['roi_amount' => '0.00', 'total_amount' => '20.00']);
        WalletTransaction::query()->where('user_id', $this->root->id)->where('reason', 'daily_roi')->delete();
        $this->root->update(['wallet_balance' => bcsub($before, '1.00', 2)]);

        $result = app(DailyIncomeService::class)->creditMissingRoi($asOf, 'admin');
        $this->assertSame(1, $result['credited']);

        $fresh = PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $fresh->roi_amount, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $fresh->referral_amount, 2, '.', ''));
        $this->assertSame('21.00', number_format((float) $fresh->total_amount, 2, '.', ''));
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $this->root->id)->where('reason', 'daily_referral')->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $this->root->id)->where('reason', 'daily_roi')->count());

        $again = app(DailyIncomeService::class)->creditMissingRoi($asOf, 'admin');
        $this->assertSame(0, $again['credited']);
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
