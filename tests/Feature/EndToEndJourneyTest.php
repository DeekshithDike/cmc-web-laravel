<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Income\DailyIncomeService;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end journey covering brand, auth, membership, payments, income, withdrawals, headers.
 */
class EndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private User $admin;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-14 12:00:00');

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'e2e-place'], 202),
            '*/internal/jobs/daily-income*' => Http::response(['ok' => true, 'jobId' => 'e2e-income'], 202),
        ]);

        config([
            'payments.default_receive' => 'manual',
            'payments.default_payout' => 'manual',
            'payments.nowpayments.api_key' => null,
            'payments.nowpayments.ipn_secret' => null,
        ]);

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::query()->create([
            'name' => 'E2E Admin',
            'email' => 'e2e-admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'E2E Root',
            'email' => 'e2e-root@citymaxcrypto.com',
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

    public function test_full_platform_journey(): void
    {
        // 1) Landing + security headers
        $landing = $this->get('/');
        $landing->assertOk();
        $landing->assertSee('City Max Crypto', false);
        $landing->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $landing->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("default-src 'self'", (string) $landing->headers->get('Content-Security-Policy'));

        // 2) Admin login + dashboard
        $this->post(route('admin.login.submit'), [
            'email' => $this->admin->email,
            'password' => 'Admin@12345',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('City Max Crypto', false);

        // 3) Admin creates member under root (left)
        $this->assertRedirectedToCredentials(
            $this->actingAs($this->admin)->post(route('admin.users.store'), [
                'name' => 'E2E Child',
                'email' => 'e2e-child@citymaxcrypto.com',
                'sponsor_id' => $this->root->id,
                'parent_id' => $this->root->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $child = User::query()->where('email', 'e2e-child@citymaxcrypto.com')->first();
        $this->assertNotNull($child);
        $this->assertTrue((bool) $child->is_active);
        $this->assertTrue((bool) $child->payment_status);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $this->root->id,
            'left_user_id' => $child->id,
        ]);

        // 4) Invite register page for right seat + register second child
        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
            'sponsorID' => $this->root->id,
        ]))->assertOk()->assertSee('City Max Crypto', false);

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'E2E Invitee',
                'email' => 'e2e-invitee@citymaxcrypto.com',
                'phone' => '9999999999',
                'country' => 'US',
                'sponsor_id' => $this->root->id,
                'parent_id' => $this->root->id,
                'position' => 'right',
                'package_id' => $this->package->id,
            ])
        );

        $this->assertNull(User::query()->where('email', 'e2e-invitee@citymaxcrypto.com')->first());
        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => null,
            'provider' => PaymentProvider::Manual->value,
            'status' => 'pending',
        ]);

        // 5) Manual payment confirm → create user, occupy seat, calc place-member
        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('admin.payments.confirm', $tx))
            ->assertRedirect();
        $this->assertSame('completed', $tx->fresh()->status);

        $invitee = User::query()->where('email', 'e2e-invitee@citymaxcrypto.com')->first();
        $this->assertNotNull($invitee);
        $this->assertTrue((bool) $invitee->is_active);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $this->root->id,
            'right_user_id' => $invitee->id,
        ]);
        $this->assertDatabaseHas('calculation_jobs', ['job_type' => 'place-member']);

        // 6) NOWPayments receive stub + IPN
        config(['payments.default_receive' => 'nowpayments']);
        $npUser = User::query()->create([
            'name' => 'NP E2E',
            'email' => 'np-e2e@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        $started = app(PaymentService::class)->start($npUser, 100, PaymentProvider::NowPayments, [
            'package_id' => $this->package->id,
        ]);
        $this->postJson(route('webhooks.payments.handle', 'nowpayments'), [
            'payment_status' => 'finished',
            'order_id' => $started['transaction']->provider_ref,
            'payment_id' => 12345,
        ])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('completed', $started['transaction']->fresh()->status);

        // 7) Customer login + pages (admin session stays independent)
        $this->assertAuthenticatedAs($this->admin, 'admin');

        $this->post(route('customer.login.submit'), [
            'login_id' => $this->root->id,
            'password' => 'Customer@123',
        ])->assertRedirect(route('customer.dashboard'));

        $this->actingAs($this->root)->get(route('customer.dashboard'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.tree'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.income.history'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.withdrawals.create'))->assertOk();

        // 8) Daily income ROI credit
        $before = (float) $this->root->fresh()->wallet_balance;
        $result = app(DailyIncomeService::class)->run(now()->toDateString());
        $this->assertGreaterThanOrEqual(1, $result['processed']);
        $this->assertGreaterThan($before, (float) $this->root->fresh()->wallet_balance);

        // 9) Withdrawal request → admin complete (manual payout)
        config(['payments.default_payout' => 'manual']);
        $this->actingAs($this->root)->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect(route('customer.withdrawals.history'));

        $withdrawal = Withdrawal::query()->latest('id')->firstOrFail();
        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);

        $this->actingAs($this->admin)
            ->post(route('admin.withdrawals.complete', $withdrawal))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(WithdrawalStatus::Completed, $withdrawal->fresh()->status);
        $this->assertSame('manual', $withdrawal->fresh()->payout_provider);

        // 10) NOWPayments payout stub
        config(['payments.default_payout' => 'nowpayments']);
        $this->root->wallet_balance = '50.00';
        $this->root->save();
        $this->actingAs($this->root)->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect();
        $npWd = Withdrawal::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.withdrawals.complete', $npWd))->assertRedirect();
        $this->assertSame(WithdrawalStatus::Completed, $npWd->fresh()->status);
        $this->assertSame('nowpayments', $npWd->fresh()->payout_provider);

        // 11) Processing withdrawals page reachable
        $this->actingAs($this->admin)
            ->get(route('admin.withdrawals.index', 'processing'))
            ->assertOk();

        // 12) Brand never leaks demo/template names on key pages
        auth('admin')->logout();
        auth('customer')->logout();
        foreach ([
            route('landing'),
            route('admin.login'),
            route('customer.login'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('hrivo', strtolower($html));
            $this->assertStringNotContainsString('softivus', strtolower($html));
            $this->assertStringNotContainsString('coinbase', strtolower($html));
            $this->assertStringNotContainsString('demo mlm', strtolower($html));
        }
    }

    public function test_payment_ipn_rejects_invalid_signature_when_secret_set(): void
    {
        config([
            'payments.nowpayments.api_key' => 'test-key',
            'payments.nowpayments.ipn_secret' => 'super-secret',
        ]);

        $this->postJson(route('webhooks.payments.handle', 'nowpayments'), [
            'payment_status' => 'finished',
            'order_id' => 'missing',
        ])->assertStatus(401)->assertJson(['ok' => false]);
    }

    public function test_decline_withdrawal_refunds_wallet(): void
    {
        $this->actingAs($this->root)->post(route('customer.withdrawals.store'), [
            'amount' => 30,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect();

        $wd = Withdrawal::query()->latest('id')->firstOrFail();
        $balanceAfterRequest = (float) $this->root->fresh()->wallet_balance;

        $this->actingAs($this->admin)->post(route('admin.withdrawals.decline', $wd), [
            'remarks' => 'bad address',
        ])->assertRedirect();

        $this->assertSame(WithdrawalStatus::Declined, $wd->fresh()->status);
        // refund = amount - fee (fee from WITHDRAWAL_FEE env) = 30 - 2 = 28
        $this->assertEqualsWithDelta($balanceAfterRequest + 28, (float) $this->root->fresh()->wallet_balance, 0.01);
    }
}
