<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\MemberCredentialsMail;
use App\Models\BinaryTree;
use App\Models\BinaryTreeLeft;
use App\Models\CalculationJob;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Services\Membership\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Mock-NOWPayments coverage for every admin sync use case: statuses, join types, duplicates, data.
 */
class AdminPaymentSyncUseCasesTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private User $admin;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'sync-place'], 202),
        ]);

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Sync Admin',
            'email' => 'sync-admin@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'Sync Root',
            'email' => 'sync-root@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
        ]);

        BinaryTree::query()->create(['users_id' => $this->root->id]);
        $this->enableNowPayments();
    }

    public function test_in_flight_and_partial_statuses_do_not_create_a_member(): void
    {
        $statuses = ['waiting', 'confirming', 'confirmed', 'sending', 'partially_paid'];
        $rows = [];
        $txs = [];
        foreach ($statuses as $i => $status) {
            $invoiceId = (string) (4522626000 + $i);
            $txs[$status] = $this->signupTransaction('hold-'.$status.'@test.com', 'left', $invoiceId);
            $rows[$invoiceId] = $this->paymentRow($invoiceId, $status);
        }
        $this->fakePayments($rows);

        $this->actingAs($this->admin)->post(route('admin.payments.sync-pending'))
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($statuses as $status) {
            $this->assertSame('pending', $txs[$status]->fresh()->status, $status.' should stay pending');
            $this->assertNull(User::query()->where('email', 'hold-'.$status.'@test.com')->first(), $status.' must not create a member');
        }
    }

    public function test_failed_refunded_expired_mark_failed_without_a_member(): void
    {
        $statuses = ['failed', 'refunded', 'expired'];
        $rows = [];
        $txs = [];
        foreach ($statuses as $i => $status) {
            $invoiceId = (string) (4522626100 + $i);
            $txs[$status] = $this->signupTransaction('fail-'.$status.'@test.com', 'right', $invoiceId);
            $rows[$invoiceId] = $this->paymentRow($invoiceId, $status);
        }
        $this->fakePayments($rows);

        $this->actingAs($this->admin)->post(route('admin.payments.sync-pending'))
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($statuses as $status) {
            $this->assertSame('failed', $txs[$status]->fresh()->status, $status.' should mark failed');
            $this->assertNull($txs[$status]->fresh()->user_id);
            $this->assertNull(User::query()->where('email', 'fail-'.$status.'@test.com')->first());
        }

        $this->assertNull(BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id'));
    }

    public function test_finished_invite_creates_member_tree_volume_referral_credentials_and_job(): void
    {
        $invoiceId = '4522626201';
        $tx = $this->signupTransaction('sync-join@test.com', 'left', $invoiceId, [
            'name' => 'Sync Join',
            'phone' => '999',
            'country' => 'US',
        ]);
        $this->fakePayments([$invoiceId => $this->paymentRow($invoiceId, 'finished', [
            'price_amount' => 100,
            'pay_amount' => 100,
            'actually_paid' => 100,
        ])]);

        $jobsBefore = CalculationJob::query()->where('job_type', 'place-member')->count();

        $this->actingAs($this->admin)
            ->from(route('admin.payments.index'))
            ->post(route('admin.payments.sync-pending'))
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'sync-join@test.com')->firstOrFail();
        $fresh = $tx->fresh();

        $this->assertSame('completed', $fresh->status);
        $this->assertSame($user->id, $fresh->user_id);
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue((bool) $user->payment_status);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame($this->root->id, $user->parent_id);
        $this->assertSame($this->root->id, $user->sponsor_id);
        $this->assertSame('left', $user->position?->value ?? $user->position);
        $this->assertSame($this->package->id, $user->package_id);
        $this->assertNotEmpty($fresh->meta['credentials_token'] ?? null);
        $this->assertSame($user->id, $fresh->meta['login_id'] ?? null);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $this->root->id,
            'left_user_id' => $user->id,
        ]);
        $this->assertSame(1, BinaryTreeLeft::query()->where('from_user_id', $user->id)->count());
        $this->assertSame(1, ReferralIncome::query()->where('user_id', $this->root->id)->where('from_user_id', $user->id)->count());
        $this->assertSame(
            $jobsBefore + 1,
            CalculationJob::query()->where('job_type', 'place-member')->count()
        );
        Mail::assertSent(MemberCredentialsMail::class, function (MemberCredentialsMail $mail) use ($user) {
            return $mail->loginId === $user->id && $mail->hasTo('sync-join@test.com');
        });

        $this->actingAs($this->admin)->post(route('admin.payments.sync-pending'))->assertRedirect();
        $this->assertSame(1, User::query()->where('email', 'sync-join@test.com')->count());
        $this->assertSame(
            $jobsBefore + 1,
            CalculationJob::query()->where('job_type', 'place-member')->count()
        );
    }

    public function test_overpay_finished_still_activates_once_same_package(): void
    {
        $invoiceId = '4522626301';
        $tx = $this->signupTransaction('overpay@test.com', 'right', $invoiceId);
        $this->fakePayments([$invoiceId => $this->paymentRow($invoiceId, 'finished', [
            'price_amount' => 100,
            'pay_amount' => 100,
            'actually_paid' => 125.5,
        ])]);

        $this->actingAs($this->admin)->post(route('admin.payments.sync-pending'))->assertRedirect();

        $user = User::query()->where('email', 'overpay@test.com')->firstOrFail();
        $this->assertSame('completed', $tx->fresh()->status);
        $this->assertSame($this->package->id, $user->package_id);
        $this->assertSame('100.00', number_format((float) $tx->fresh()->amount, 2, '.', ''));
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $this->root->id,
            'right_user_id' => $user->id,
        ]);
    }

    public function test_redeposit_child_is_ignored_and_original_partial_stays_pending(): void
    {
        $invoiceId = '4522626401';
        $tx = $this->signupTransaction('redeposit@test.com', 'left', $invoiceId);
        $this->fakePayments([$invoiceId => [
            $this->paymentRow($invoiceId, 'finished', ['payment_id' => 99, 'parent_payment_id' => 88]),
            $this->paymentRow($invoiceId, 'partially_paid', ['payment_id' => 88, 'parent_payment_id' => null]),
        ]]);

        $this->actingAs($this->admin)->post(route('admin.payments.sync-pending'))->assertRedirect();

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertNull(User::query()->where('email', 'redeposit@test.com')->first());
    }

    public function test_power_id_finished_sync_activates_existing_id(): void
    {
        $dummy = app(MembershipService::class)->createPowerId($this->root->id, $this->root->id, 'left');
        $invoiceId = '4522626501';
        PaymentTransaction::query()->create([
            'user_id' => null,
            'package_id' => $this->package->id,
            'provider' => PaymentProvider::NowPayments,
            'provider_ref' => $invoiceId,
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'pending',
            'meta' => [
                'order_id' => 'CMC-PWR-'.$invoiceId,
                'invoice' => ['id' => $invoiceId],
                'power_activation' => [
                    'power_id' => $dummy->id,
                    'name' => 'Power Guest',
                    'email' => 'power-sync@test.com',
                    'package_id' => $this->package->id,
                ],
            ],
        ]);
        $this->fakePayments([$invoiceId => $this->paymentRow($invoiceId, 'finished', ['price_amount' => 100])]);

        $this->actingAs($this->admin)->post(route('admin.payments.sync-pending'))->assertRedirect();

        $activated = $dummy->fresh();
        $this->assertFalse((bool) $activated->is_power_id);
        $this->assertTrue((bool) $activated->is_active);
        $this->assertTrue((bool) $activated->payment_status);
        $this->assertSame('power-sync@test.com', $activated->email);
        $this->assertSame($dummy->id, BinaryTree::query()->where('users_id', $this->root->id)->value('left_user_id'));
        Mail::assertSent(MemberCredentialsMail::class, function (MemberCredentialsMail $mail) use ($activated) {
            return $mail->loginId === $activated->id && $mail->hasTo('power-sync@test.com');
        });
    }

    public function test_existing_unpaid_member_is_activated_and_completed_rows_are_untouched(): void
    {
        $unpaid = User::query()->create([
            'name' => 'Unpaid',
            'email' => 'unpaid-sync@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Inactive,
            'is_active' => false,
            'payment_status' => false,
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
            'package_id' => $this->package->id,
        ]);
        BinaryTree::query()->create([
            'users_id' => $unpaid->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
        ]);
        $rootTree = BinaryTree::query()->where('users_id', $this->root->id)->firstOrFail();
        $rootTree->right_user_id = $unpaid->id;
        $rootTree->save();

        $pending = PaymentTransaction::query()->create([
            'user_id' => $unpaid->id,
            'package_id' => $this->package->id,
            'provider' => PaymentProvider::NowPayments,
            'provider_ref' => '4522626601',
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'pending',
            'meta' => ['invoice' => ['id' => '4522626601']],
        ]);
        $done = PaymentTransaction::query()->create([
            'user_id' => $this->root->id,
            'package_id' => $this->package->id,
            'provider' => PaymentProvider::NowPayments,
            'provider_ref' => '4522626602',
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'completed',
            'meta' => ['invoice' => ['id' => '4522626602']],
        ]);
        $stub = PaymentTransaction::query()->create([
            'user_id' => $this->root->id,
            'package_id' => $this->package->id,
            'provider' => PaymentProvider::NowPayments,
            'provider_ref' => 'CMC-STUB',
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'pending',
            'meta' => ['stub' => true],
        ]);

        $this->fakePayments([
            '4522626601' => $this->paymentRow('4522626601', 'finished', ['price_amount' => 100]),
            '4522626602' => $this->paymentRow('4522626602', 'failed'),
        ]);

        $this->actingAs($this->admin)->post(route('admin.payments.sync-pending'))->assertRedirect();

        $this->assertSame('completed', $pending->fresh()->status);
        $this->assertTrue((bool) $unpaid->fresh()->payment_status);
        $this->assertTrue((bool) $unpaid->fresh()->is_active);
        $this->assertSame('completed', $done->fresh()->status);
        $this->assertSame('pending', $stub->fresh()->status);
    }

    public function test_api_error_leaves_row_pending_and_guest_cannot_sync(): void
    {
        $tx = $this->signupTransaction('api-error@test.com', 'left', '4522626701');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth')) {
                return Http::response(['token' => 'jwt'], 200);
            }
            if ($request->method() === 'GET' && str_contains((string) parse_url($request->url(), PHP_URL_PATH), '/payment')) {
                return Http::response(['status' => false, 'message' => 'upstream down'], 500);
            }

            return Http::response(['ok' => true], 200);
        });

        $this->actingAs($this->admin)
            ->from(route('admin.payments.index'))
            ->post(route('admin.payments.sync-pending'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertNull(User::query()->where('email', 'api-error@test.com')->first());

        Auth::guard('admin')->logout();
        $this->flushSession();
        $this->post(route('admin.payments.sync-pending'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->root)->post(route('admin.payments.sync-pending'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_confirm_and_payment_ipn_still_work_after_sync_feature(): void
    {
        $confirmTx = $this->signupTransaction('still-confirm@test.com', 'left', '4522626801');
        $this->actingAs($this->admin)
            ->post(route('admin.payments.confirm', $confirmTx))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNotNull(User::query()->where('email', 'still-confirm@test.com')->first());

        config(['payments.nowpayments.ipn_secret' => 'ipn-secret']);
        $ipnTx = $this->signupTransaction('still-ipn@test.com', 'right', '4522626802');
        $payload = [
            'payment_status' => 'finished',
            'invoice_id' => 4522626802,
            'payment_id' => 6802,
            'price_amount' => 100,
        ];
        ksort($payload);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha512', $body, 'ipn-secret');
        $this->call(
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
        )->assertOk()->assertJson(['status' => 'completed']);

        $this->assertSame('completed', $ipnTx->fresh()->status);
        $this->assertNotNull(User::query()->where('email', 'still-ipn@test.com')->first());
    }

    private function enableNowPayments(): void
    {
        config([
            'payments.default_receive' => 'nowpayments',
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
            'payments.nowpayments.email' => 'np@test.com',
            'payments.nowpayments.password' => 'np-pass',
            'payments.nowpayments.price_currency' => 'usd',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extraSignup
     */
    private function signupTransaction(string $email, string $position, string $invoiceId, array $extraSignup = []): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'user_id' => null,
            'package_id' => $this->package->id,
            'provider' => PaymentProvider::NowPayments,
            'provider_ref' => $invoiceId,
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'pending',
            'meta' => [
                'order_id' => 'CMC-INV-'.$invoiceId,
                'invoice' => ['id' => $invoiceId],
                'signup' => array_merge([
                    'name' => 'Join '.$email,
                    'email' => $email,
                    'package_id' => $this->package->id,
                    'parent_id' => $this->root->id,
                    'position' => $position,
                    'sponsor_id' => $this->root->id,
                ], $extraSignup),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paymentRow(string $invoiceId, string $status, array $extra = []): array
    {
        return array_merge([
            'payment_id' => (int) $invoiceId,
            'invoice_id' => (int) $invoiceId,
            'payment_status' => $status,
            'price_amount' => 100,
            'price_currency' => 'usd',
        ], $extra);
    }

    /**
     * @param  array<string, array<string, mixed>|list<array<string, mixed>>>  $byInvoice
     */
    private function fakePayments(array $byInvoice): void
    {
        Http::fake(function ($request) use ($byInvoice) {
            $url = $request->url();
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (str_contains($url, '/internal/jobs/place-member')) {
                return Http::response(['ok' => true, 'jobId' => 'sync-place'], 202);
            }
            if (str_contains($url, '/auth')) {
                return Http::response(['token' => 'jwt'], 200);
            }
            if ($request->method() === 'GET' && str_ends_with(rtrim($path, '/'), '/payment')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $invoiceId = (string) ($query['invoiceId'] ?? '');
                $rows = $byInvoice[$invoiceId] ?? [];
                if ($rows !== [] && array_is_list($rows) === false && isset($rows['payment_status'])) {
                    $rows = [$rows];
                }

                return Http::response(['data' => array_values($rows), 'total' => count($rows)], 200);
            }

            return Http::response(['ok' => true], 200);
        });
    }
}
