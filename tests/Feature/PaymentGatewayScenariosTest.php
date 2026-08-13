<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\TreePosition;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\BinaryTreeLeft;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use App\Services\Payments\PaymentService;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Payment gateway E2E scenarios: success, failure, retries, duplicates, cancel/retry, payouts.
 * Asserts HTTP outcomes and database row state.
 */
class PaymentGatewayScenariosTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private User $root;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'place-1'], 202),
            '*/internal/jobs/daily-income*' => Http::response(['ok' => true], 202),
        ]);

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-scenarios@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'Root',
            'email' => 'root-scenarios@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'wallet_balance' => '200.00',
        ]);

        BinaryTree::query()->create(['users_id' => $this->root->id]);
    }

    private function signedIpn(array $payload, string $secret = 'ipn-secret'): array
    {
        ksort($payload);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha512', $body, $secret);

        return [$body, $sig];
    }

    private function postSignedPaymentIpn(array $payload, string $secret = 'ipn-secret')
    {
        [$body, $sig] = $this->signedIpn($payload, $secret);

        return $this->call(
            'POST',
            route('webhooks.payments.handle', 'nowpayments'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_NOWPAYMENTS_SIG' => $sig,
            ],
            $body
        );
    }

    private function postSignedPayoutIpn(array $payload, string $secret = 'ipn-secret')
    {
        [$body, $sig] = $this->signedIpn($payload, $secret);

        return $this->call(
            'POST',
            route('webhooks.payouts.handle', 'nowpayments'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_NOWPAYMENTS_SIG' => $sig,
            ],
            $body
        );
    }

    private function enableLiveNowPaymentsReceive(): void
    {
        config([
            'payments.default_receive' => 'nowpayments',
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
            'payments.nowpayments.price_currency' => 'usd',
        ]);
    }

    private function enableLiveNowPaymentsPayout(): void
    {
        config([
            'payments.default_payout' => 'nowpayments',
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
            'payments.nowpayments.email' => 'np@test.com',
            'payments.nowpayments.password' => 'np-pass',
            'payments.nowpayments.two_fa_secret' => 'JBSWY3DPEHPK3PXP',
            'payments.nowpayments.validate_payout_address' => true,
            'payments.nowpayments.payout_currency' => 'usdttrc20',
            'payments.nowpayments.payout_fiat_currency' => 'usd',
        ]);
    }

    private function createAwaitingMember(string $email = 'awaiting@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Awaiting',
            'email' => $email,
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Inactive,
            'is_active' => false,
            'payment_status' => false,
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => TreePosition::Left,
            'package_id' => $this->package->id,
            'wallet_balance' => '0.00',
        ]);

        BinaryTree::query()->create([
            'users_id' => $user->id,
            'parent_id' => $this->root->id,
            'position' => TreePosition::Left,
        ]);

        $tree = BinaryTree::query()->where('users_id', $this->root->id)->firstOrFail();
        $tree->left_user_id = $user->id;
        $tree->save();

        return $user;
    }

    public function test_live_invoice_success_ipn_activates_member_and_writes_db(): void
    {
        $this->enableLiveNowPaymentsReceive();

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/invoice' => Http::response([
                'id' => '4522625843',
                'invoice_url' => 'https://nowpayments.io/payment/?iid=4522625843',
                'order_id' => 'will-be-replaced',
            ], 201),
        ]);

        $user = $this->createAwaitingMember('success@test.com');
        $result = app(PaymentService::class)->start($user, 100, PaymentProvider::NowPayments, [
            'package_id' => $this->package->id,
            'source' => 'invite_register',
        ]);

        $tx = $result['transaction'];
        $this->assertSame('https://nowpayments.io/payment/?iid=4522625843', $result['redirect_url']);
        $this->assertDatabaseHas('payment_transactions', [
            'id' => $tx->id,
            'user_id' => $user->id,
            'provider' => 'nowpayments',
            'provider_ref' => '4522625843',
            'amount' => '100.00',
            'status' => 'pending',
        ]);
        $this->assertFalse((bool) $user->fresh()->payment_status);

        $this->postSignedPaymentIpn([
            'payment_id' => 111,
            'invoice_id' => 4522625843,
            'payment_status' => 'finished',
            'order_id' => $tx->meta['order_id'] ?? $tx->provider_ref,
            'price_amount' => 100,
            'price_currency' => 'usd',
        ])->assertOk()->assertJson(['ok' => true, 'status' => 'completed']);

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $tx->id,
            'status' => 'completed',
        ]);

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->payment_status);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame(UserStatus::Active, $fresh->status);
        $this->assertNotNull($fresh->expiry_date);
        $this->assertDatabaseHas('calculation_jobs', ['job_type' => 'place-member']);
        $this->assertDatabaseHas('binary_tree_lefts', [
            'user_id' => $this->root->id,
            'from_user_id' => $user->id,
        ]);
    }

    public function test_ipn_waiting_and_confirming_keep_pending(): void
    {
        $this->enableLiveNowPaymentsReceive();
        config(['payments.nowpayments.api_key' => null]); // stub initiate

        $user = $this->createAwaitingMember('pending-ipn@test.com');
        $tx = app(PaymentService::class)->start($user, 100, PaymentProvider::NowPayments, [
            'package_id' => $this->package->id,
        ])['transaction'];

        config([
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
        ]);

        foreach (['waiting', 'confirming', 'confirmed', 'sending', 'partially_paid'] as $status) {
            $this->postSignedPaymentIpn([
                'payment_status' => $status,
                'order_id' => $tx->meta['order_id'] ?? $tx->provider_ref,
                'payment_id' => 1,
            ])->assertOk()->assertJson(['ok' => true, 'status' => 'pending']);

            $this->assertDatabaseHas('payment_transactions', [
                'id' => $tx->id,
                'status' => 'pending',
            ]);
            $this->assertFalse((bool) $user->fresh()->payment_status);
        }
    }

    public function test_ipn_failed_and_expired_mark_failed_without_activating(): void
    {
        $this->enableLiveNowPaymentsReceive();
        config(['payments.nowpayments.api_key' => null]);

        $user = $this->createAwaitingMember('fail-ipn@test.com');
        $tx = app(PaymentService::class)->start($user, 100, PaymentProvider::NowPayments)['transaction'];

        config([
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
        ]);

        $this->postSignedPaymentIpn([
            'payment_status' => 'expired',
            'order_id' => $tx->meta['order_id'] ?? $tx->provider_ref,
            'payment_id' => 2,
        ])->assertOk()->assertJson(['ok' => true, 'status' => 'failed']);

        $this->assertDatabaseHas('payment_transactions', ['id' => $tx->id, 'status' => 'failed']);
        $this->assertFalse((bool) $user->fresh()->payment_status);
        $this->assertSame(0, BinaryTreeLeft::query()->where('from_user_id', $user->id)->count());
    }

    public function test_duplicate_finished_ipn_is_idempotent(): void
    {
        $this->enableLiveNowPaymentsReceive();
        config(['payments.nowpayments.api_key' => null]);

        $user = $this->createAwaitingMember('dup@test.com');
        $tx = app(PaymentService::class)->start($user, 100, PaymentProvider::NowPayments)['transaction'];

        config([
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
        ]);

        $payload = [
            'payment_status' => 'finished',
            'order_id' => $tx->meta['order_id'] ?? $tx->provider_ref,
            'payment_id' => 55,
        ];

        $this->postSignedPaymentIpn($payload)->assertOk()->assertJson(['status' => 'completed']);
        $jobsAfterFirst = \App\Models\CalculationJob::query()->where('job_type', 'place-member')->count();

        $this->postSignedPaymentIpn($payload)
            ->assertOk()
            ->assertJson(['ok' => true, 'idempotent' => true, 'status' => 'completed']);

        $this->assertSame(
            $jobsAfterFirst,
            \App\Models\CalculationJob::query()->where('job_type', 'place-member')->count()
        );
        $this->assertSame(1, PaymentTransaction::query()->where('user_id', $user->id)->where('status', 'completed')->count());
        $this->assertTrue((bool) $user->fresh()->payment_status);
    }

    public function test_invalid_signature_and_unknown_order_rejected(): void
    {
        $this->enableLiveNowPaymentsReceive();

        $this->call(
            'POST',
            route('webhooks.payments.handle', 'nowpayments'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_NOWPAYMENTS_SIG' => 'bad-sig',
            ],
            json_encode(['payment_status' => 'finished', 'order_id' => 'x'])
        )->assertStatus(401);

        [$body, $sig] = $this->signedIpn([
            'payment_status' => 'finished',
            'order_id' => 'CMC-DOES-NOT-EXIST',
            'payment_id' => 9,
        ]);

        $this->call(
            'POST',
            route('webhooks.payments.handle', 'nowpayments'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_NOWPAYMENTS_SIG' => $sig,
            ],
            $body
        )->assertStatus(404)->assertJson(['error' => 'Transaction not found']);
    }

    public function test_cancel_then_retry_creates_second_pending_invoice(): void
    {
        $this->enableLiveNowPaymentsReceive();

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/invoice' => Http::sequence()
                ->push([
                    'id' => 'inv-1',
                    'invoice_url' => 'https://nowpayments.io/payment/?iid=inv-1',
                ], 201)
                ->push([
                    'id' => 'inv-2',
                    'invoice_url' => 'https://nowpayments.io/payment/?iid=inv-2',
                ], 201),
        ]);

        $user = $this->createAwaitingMember('retry@test.com');
        $payments = app(PaymentService::class);

        $first = $payments->start($user, 100, PaymentProvider::NowPayments, ['package_id' => $this->package->id]);
        $this->assertSame('inv-1', $first['transaction']->provider_ref);

        // User cancelled / expired first checkout — mark failed then retry.
        $payments->confirm($first['transaction'], ['status' => 'failed', 'reason' => 'cancelled']);
        $this->assertDatabaseHas('payment_transactions', [
            'id' => $first['transaction']->id,
            'status' => 'failed',
        ]);
        $this->assertFalse((bool) $user->fresh()->payment_status);

        $second = $payments->start($user, 100, PaymentProvider::NowPayments, ['package_id' => $this->package->id]);
        $this->assertSame('inv-2', $second['transaction']->provider_ref);
        $this->assertSame('pending', $second['transaction']->status);
        $this->assertSame(2, PaymentTransaction::query()->where('user_id', $user->id)->count());

        $this->postSignedPaymentIpn([
            'payment_status' => 'finished',
            'order_id' => $second['transaction']->meta['order_id'],
            'invoice_id' => 'inv-2',
            'payment_id' => 77,
        ])->assertOk();

        $this->assertTrue((bool) $user->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', [
            'id' => $second['transaction']->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'id' => $first['transaction']->id,
            'status' => 'failed',
        ]);
    }

    public function test_invoice_api_failure_marks_transaction_failed(): void
    {
        $this->enableLiveNowPaymentsReceive();

        Http::fake([
            '*/invoice' => Http::response(['message' => 'server error'], 500),
        ]);

        $user = $this->createAwaitingMember('api-fail@test.com');

        try {
            app(PaymentService::class)->start($user, 100, PaymentProvider::NowPayments, [
                'package_id' => $this->package->id,
            ]);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Unable to start NOWPayments checkout', $e->getMessage());
        }

        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $user->id,
            'provider' => 'nowpayments',
            'status' => 'failed',
        ]);
        $this->assertFalse((bool) $user->fresh()->payment_status);
    }

    public function test_invite_register_live_redirects_to_invoice_and_awaits_payment(): void
    {
        $this->enableLiveNowPaymentsReceive();

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/invoice' => Http::response([
                'id' => 'reg-inv-9',
                'invoice_url' => 'https://nowpayments.io/payment/?iid=reg-inv-9',
            ], 201),
        ]);

        $response = $this->post(route('customer.register.save'), [
            'name' => 'Invitee Live',
            'email' => 'invitee-live@test.com',
            'phone' => '111',
            'country' => 'US',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
            'package_id' => $this->package->id,
        ]);

        $location = $this->assertRedirectedToCredentials($response);
        $this->get($location)
            ->assertOk()
            ->assertSee('https://nowpayments.io/payment/?iid=reg-inv-9', false)
            ->assertSee((string) User::query()->where('email', 'invitee-live@test.com')->value('id'), false);

        $user = User::query()->where('email', 'invitee-live@test.com')->firstOrFail();
        $this->assertFalse((bool) $user->payment_status);
        $this->assertFalse((bool) $user->is_active);
        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $user->id,
            'provider' => 'nowpayments',
            'provider_ref' => 'reg-inv-9',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $this->root->id,
            'right_user_id' => $user->id,
        ]);

        // Login blocked until paid (set known password to isolate payment gate, not credential hash)
        $user->password = 'Customer@123';
        $user->save();

        $this->post(route('customer.login.submit'), [
            'login_id' => $user->id,
            'password' => 'Customer@123',
        ])->assertSessionHasErrors(['login_id']);
    }

    public function test_manual_confirm_twice_does_not_double_dispatch(): void
    {
        config(['payments.default_receive' => 'manual']);

        $user = User::query()->create([
            'name' => 'Manual',
            'email' => 'manual@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $payments = app(PaymentService::class);
        $tx = $payments->start($user, 100, PaymentProvider::Manual, ['package_id' => $this->package->id])['transaction'];

        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))->assertRedirect();
        $count = \App\Models\CalculationJob::query()->where('job_type', 'place-member')->count();

        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))->assertRedirect();
        $this->assertSame(
            $count,
            \App\Models\CalculationJob::query()->where('job_type', 'place-member')->count()
        );
        $this->assertDatabaseHas('payment_transactions', ['id' => $tx->id, 'status' => 'completed']);
    }

    public function test_payout_invalid_address_leaves_withdrawal_pending(): void
    {
        $this->enableLiveNowPaymentsPayout();

        Http::fake([
            '*/auth' => Http::response(['token' => 'jwt'], 200),
            '*/payout/validate-address' => Http::response(['status' => 'ERROR', 'message' => 'bad'], 200),
        ]);

        $wd = Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => 'bad-address',
            'status' => WithdrawalStatus::Pending,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.withdrawals.complete', $wd))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('withdrawals', [
            'id' => $wd->id,
            'status' => WithdrawalStatus::Pending->value,
        ]);
        $this->assertNull($wd->fresh()->payout_ref);
    }

    public function test_payout_success_then_failed_ipn_refunds_once(): void
    {
        $this->enableLiveNowPaymentsPayout();

        Http::fake([
            '*/auth' => Http::response(['token' => 'jwt'], 200),
            '*/payout/validate-address' => Http::response(['status' => 'OK'], 200),
            '*/payout/*/verify' => Http::response(['status' => 'OK'], 200),
            '*/payout' => Http::response([
                'id' => 'batch-9',
                'withdrawals' => [['id' => 'wd-item-9', 'status' => 'CREATING']],
            ], 200),
        ]);

        $before = (float) $this->root->wallet_balance;
        app(WithdrawalService::class)->request($this->root, 25, 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS');
        $wd = Withdrawal::query()->latest('id')->firstOrFail();
        $afterDebit = (float) $this->root->fresh()->wallet_balance;
        $this->assertEqualsWithDelta($before - 25, $afterDebit, 0.01);

        $this->actingAs($this->admin)->post(route('admin.withdrawals.complete', $wd))->assertRedirect();
        $this->assertDatabaseHas('withdrawals', [
            'id' => $wd->id,
            'status' => WithdrawalStatus::Processing->value,
            'payout_ref' => 'wd-item-9',
            'payout_provider' => 'nowpayments',
        ]);

        $this->postSignedPayoutIpn([
            'id' => 'wd-item-9',
            'batch_withdrawal_id' => 'batch-9',
            'status' => 'FAILED',
            'extra_id' => (string) $wd->id,
        ])->assertOk();

        $this->assertSame(WithdrawalStatus::Declined, $wd->fresh()->status);
        // refund amount - fee = 20
        $this->assertEqualsWithDelta($afterDebit + 20, (float) $this->root->fresh()->wallet_balance, 0.01);

        // Duplicate failed IPN must not double-refund
        $balance = (float) $this->root->fresh()->wallet_balance;
        $this->postSignedPayoutIpn([
            'id' => 'wd-item-9',
            'status' => 'FAILED',
        ])->assertOk();
        $this->assertEqualsWithDelta($balance, (float) $this->root->fresh()->wallet_balance, 0.01);
    }

    public function test_payout_finished_ipn_completes_and_duplicate_is_safe(): void
    {
        $this->enableLiveNowPaymentsPayout();
        config(['payments.nowpayments.api_key' => null]); // stub send completes immediately — use processing row instead

        $wd = Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
            'status' => WithdrawalStatus::Processing,
            'payout_provider' => 'nowpayments',
            'payout_ref' => 'wd-ok-1',
            'meta' => ['batch_id' => 'batch-ok'],
        ]);

        config([
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
        ]);

        $this->postSignedPayoutIpn([
            'id' => 'wd-ok-1',
            'batch_withdrawal_id' => 'batch-ok',
            'status' => 'FINISHED',
        ])->assertOk()->assertJson(['status' => WithdrawalStatus::Completed->value]);

        $this->assertDatabaseHas('withdrawals', [
            'id' => $wd->id,
            'status' => WithdrawalStatus::Completed->value,
        ]);
        $this->assertNotNull($wd->fresh()->processed_at);

        $this->postSignedPayoutIpn([
            'id' => 'wd-ok-1',
            'status' => 'FINISHED',
        ])->assertOk();

        $this->assertSame(WithdrawalStatus::Completed, $wd->fresh()->status);
    }

    public function test_multiple_concurrent_pending_checkouts_only_activate_once(): void
    {
        $this->enableLiveNowPaymentsReceive();

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/invoice' => Http::sequence()
                ->push(['id' => 'm1', 'invoice_url' => 'https://nowpayments.io/payment/?iid=m1'], 201)
                ->push(['id' => 'm2', 'invoice_url' => 'https://nowpayments.io/payment/?iid=m2'], 201),
        ]);

        $user = $this->createAwaitingMember('multi@test.com');
        $payments = app(PaymentService::class);
        $a = $payments->start($user, 100, PaymentProvider::NowPayments)['transaction'];
        $b = $payments->start($user, 100, PaymentProvider::NowPayments)['transaction'];

        $this->assertNotSame($a->id, $b->id);

        $this->postSignedPaymentIpn([
            'payment_status' => 'finished',
            'order_id' => $a->meta['order_id'],
            'invoice_id' => 'm1',
        ])->assertOk();

        $this->assertTrue((bool) $user->fresh()->payment_status);

        $this->postSignedPaymentIpn([
            'payment_status' => 'finished',
            'order_id' => $b->meta['order_id'],
            'invoice_id' => 'm2',
        ])->assertOk();

        $this->assertSame(2, PaymentTransaction::query()->where('user_id', $user->id)->where('status', 'completed')->count());
        // Volume recorded once (first activation path)
        $this->assertSame(1, BinaryTreeLeft::query()->where('from_user_id', $user->id)->count());
    }

    public function test_totp_and_client_helpers_are_configured_from_env_config(): void
    {
        config(['payments.nowpayments.two_fa_secret' => 'JBSWY3DPEHPK3PXP']);
        $code = app(NowPaymentsClient::class)->generateTotpCode(null, 1_700_000_000);
        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));
    }
}
