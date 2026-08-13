<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\MemberCredentialsMail;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private User $admin;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'invite-place'], 202),
        ]);

        config([
            'payments.default_receive' => 'manual',
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
            'name' => 'Admin',
            'email' => 'admin-invite@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'Root',
            'email' => 'root-invite@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'wallet_balance' => '0.00',
        ]);

        BinaryTree::query()->create(['users_id' => $this->root->id]);
    }

    public function test_payment_success_and_cancel_pages_match_reference_copy(): void
    {
        $this->get(route('customer.payment.success'))
            ->assertOk()
            ->assertSee('Registration successful', false)
            ->assertSee('Login ID and Password will be sent to your registered email address after activation', false);

        $this->get(route('customer.payment.cancel'))
            ->assertOk()
            ->assertSee('Payment cancelled', false)
            ->assertSee('Signup again using the link', false);
    }

    public function test_invite_submit_does_not_create_user_or_occupy_slot(): void
    {
        $this->submitInvite('first@test.com', 'right');

        $this->assertNull(User::query()->where('email', 'first@test.com')->first());
        $this->assertNull(BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id'));

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->assertNull($tx->user_id);
        $this->assertSame('pending', $tx->status);
        $this->assertSame('first@test.com', $tx->meta['signup']['email'] ?? null);
        $this->assertSame($this->root->id, (int) ($tx->meta['signup']['parent_id'] ?? 0));
    }

    public function test_unpaid_invite_leaves_slot_free_for_another_signup(): void
    {
        $this->submitInvite('first@test.com', 'right');
        $this->submitInvite('second@test.com', 'right');

        $this->assertSame(2, PaymentTransaction::query()->where('status', 'pending')->count());
        $this->assertNull(User::query()->where('email', 'first@test.com')->first());
        $this->assertNull(User::query()->where('email', 'second@test.com')->first());
        $this->assertNull(BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id'));
    }

    public function test_payment_confirm_creates_user_places_tree_and_emails_credentials(): void
    {
        Mail::fake();

        $this->submitInvite('paid@test.com', 'right');
        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'paid@test.com')->firstOrFail();
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue((bool) $user->payment_status);
        $this->assertSame($this->root->id, $user->sponsor_id);
        $this->assertSame($this->root->id, $user->parent_id);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $this->root->id,
            'right_user_id' => $user->id,
        ]);
        $this->assertSame(1, ReferralIncome::query()->where('user_id', $this->root->id)->where('from_user_id', $user->id)->count());
        $this->assertSame('100.00', number_format((float) ReferralIncome::query()->where('user_id', $this->root->id)->where('from_user_id', $user->id)->value('amount'), 2, '.', ''));
        $this->assertDatabaseHas('binary_tree_rights', [
            'user_id' => $this->root->id,
            'from_user_id' => $user->id,
            'amount' => '100.00',
        ]);
        $this->assertSame($user->id, $tx->fresh()->user_id);

        Mail::assertSent(MemberCredentialsMail::class, function (MemberCredentialsMail $mail) use ($user) {
            return $mail->loginId === $user->id && $mail->hasTo('paid@test.com');
        });

        $token = $tx->fresh()->meta['credentials_token'] ?? null;
        $this->assertNotEmpty($token);
        $this->get(route('customer.payment.success', ['ref' => $tx->meta['order_id'] ?? $tx->provider_ref]))
            ->assertOk()
            ->assertSee('View login details', false);
        $this->get(route('credentials.show', ['token' => $token]))
            ->assertOk()
            ->assertSee((string) $user->id, false);
    }

    public function test_second_payment_loses_if_slot_already_taken(): void
    {
        $this->submitInvite('winner@test.com', 'right');
        $this->submitInvite('loser@test.com', 'right');

        $first = PaymentTransaction::query()->orderBy('id')->firstOrFail();
        $second = PaymentTransaction::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $first))->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $second))->assertRedirect();

        $this->assertNotNull(User::query()->where('email', 'winner@test.com')->first());
        $this->assertNull(User::query()->where('email', 'loser@test.com')->first());
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertNotEmpty($second->fresh()->meta['activation_error'] ?? null);
        $this->assertSame(
            User::query()->where('email', 'winner@test.com')->value('id'),
            BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id')
        );
    }

    public function test_live_nowpayments_redirects_to_invoice_without_locking_the_seat(): void
    {
        config([
            'payments.default_receive' => 'nowpayments',
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
        ]);

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/invoice' => Http::response([
                'id' => 'inv-flow-1',
                'invoice_url' => 'https://nowpayments.io/payment/?iid=inv-flow-1',
            ], 201),
        ]);

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'left',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->post(route('customer.register.save'), [
            'name' => 'Live Invitee',
            'email' => 'live-invitee@test.com',
            'package_id' => $this->package->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
        ])->assertRedirect('https://nowpayments.io/payment/?iid=inv-flow-1');

        $this->assertNull(User::query()->where('email', 'live-invitee@test.com')->first());
        $this->assertNull(BinaryTree::query()->where('users_id', $this->root->id)->value('left_user_id'));
        $this->assertTrue(app(PaymentService::class)->requiresLiveCheckout());
    }

    public function test_finished_ipn_with_invoice_id_only_creates_the_member(): void
    {
        config([
            'payments.default_receive' => 'nowpayments',
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
        ]);

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/invoice' => Http::response([
                'id' => 4522625843,
                'invoice_url' => 'https://nowpayments.io/payment/?iid=4522625843',
            ], 201),
        ]);

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'left',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->post(route('customer.register.save'), [
            'name' => 'Ipn Invitee',
            'email' => 'ipn-invitee@test.com',
            'package_id' => $this->package->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
        ])->assertRedirect('https://nowpayments.io/payment/?iid=4522625843');

        $this->assertNull(User::query()->where('email', 'ipn-invitee@test.com')->first());

        $payload = [
            'payment_id' => 987654321,
            'invoice_id' => 4522625843,
            'payment_status' => 'finished',
            'price_amount' => 100,
            'price_currency' => 'usd',
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
        )->assertOk()->assertJson(['ok' => true, 'status' => 'completed']);

        $user = User::query()->where('email', 'ipn-invitee@test.com')->firstOrFail();
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue((bool) $user->payment_status);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $this->root->id,
            'left_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $user->id,
            'provider_ref' => '4522625843',
            'status' => 'completed',
        ]);
    }

    private function submitInvite(string $email, string $position): void
    {
        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => $position,
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Invitee',
                'email' => $email,
                'phone' => '111',
                'country' => 'US',
                'sponsor_id' => $this->root->id,
                'parent_id' => $this->root->id,
                'position' => $position,
                'package_id' => $this->package->id,
            ])
        );
    }
}
