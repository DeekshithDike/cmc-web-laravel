<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTree;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Income\DailyIncomeService;
use App\Services\Membership\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BinaryTreePlacementAndIncomeTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private User $admin;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-14 12:00:00');
        Mail::fake();
        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/internal/jobs/daily-income*' => Http::response(['ok' => true], 202),
        ]);

        config([
            'payments.default_receive' => 'manual',
            'payments.nowpayments.api_key' => null,
            'payments.nowpayments.ipn_secret' => null,
            'citymax.income.binary_percent' => 5,
            'citymax.income.binary_max' => 500,
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
            'email' => 'admin-tree@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'Senior A',
            'email' => 'senior-a@test.com',
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

    public function test_multi_level_ids_volume_and_binary_match_reference_behaviour(): void
    {
        $b = $this->addMember('B', $this->root, 'left');
        $c = $this->addMember('C', $this->root, 'right');
        $d = $this->addMember('D', $b, 'left');
        $e = $this->addMember('E', $b, 'right');
        $f = $this->addMember('F', $c, 'left');
        $g = $this->addMember('G', $c, 'right');
        $h = $this->addMember('H', $d, 'left');

        $this->assertSame($b->id, BinaryTree::query()->where('users_id', $this->root->id)->value('left_user_id'));
        $this->assertSame($c->id, BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id'));
        $this->assertSame($d->id, BinaryTree::query()->where('users_id', $b->id)->value('left_user_id'));
        $this->assertSame($e->id, BinaryTree::query()->where('users_id', $b->id)->value('right_user_id'));
        $this->assertSame($h->id, BinaryTree::query()->where('users_id', $d->id)->value('left_user_id'));

        $this->assertSame('400.00', $this->sideTotal($this->root->id, 'left'));
        $this->assertSame('300.00', $this->sideTotal($this->root->id, 'right'));
        $this->assertSame('200.00', $this->sideTotal($b->id, 'left'));
        $this->assertSame('100.00', $this->sideTotal($b->id, 'right'));
        $this->assertSame('100.00', $this->sideTotal($c->id, 'left'));
        $this->assertSame('100.00', $this->sideTotal($c->id, 'right'));
        $this->assertSame('100.00', $this->sideTotal($d->id, 'left'));
        $this->assertSame('0.00', $this->sideTotal($d->id, 'right'));
        $this->assertSame('0.00', $this->sideTotal($e->id, 'left'));
        $this->assertSame('0.00', $this->sideTotal($h->id, 'left'));

        $this->assertDatabaseHas('binary_tree_lefts', [
            'user_id' => $this->root->id,
            'from_user_id' => $h->id,
            'amount' => '100.00',
        ]);
        $this->assertDatabaseHas('binary_tree_lefts', [
            'user_id' => $b->id,
            'from_user_id' => $h->id,
            'amount' => '100.00',
        ]);
        $this->assertDatabaseHas('binary_tree_rights', [
            'user_id' => $b->id,
            'from_user_id' => $e->id,
            'amount' => '100.00',
        ]);
        $this->assertDatabaseHas('binary_tree_lefts', [
            'user_id' => $this->root->id,
            'from_user_id' => $e->id,
            'amount' => '100.00',
        ]);

        $asOf = now()->toDateString();
        app(DailyIncomeService::class)->run($asOf);

        $this->assertSame('15.00', $this->binaryPay($this->root->id, $asOf));
        $this->assertSame('5.00', $this->binaryPay($b->id, $asOf));
        $this->assertSame('5.00', $this->binaryPay($c->id, $asOf));
        $this->assertSame('0.00', $this->binaryPay($d->id, $asOf));
        $this->assertSame('0.00', $this->binaryPay($h->id, $asOf));

        $aCarry = CarryForward::query()->where('user_id', $this->root->id)->whereDate('as_of', $asOf)->firstOrFail();
        $this->assertSame('100.00', number_format((float) $aCarry->left_carry, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $aCarry->right_carry, 2, '.', ''));

        $dCarry = CarryForward::query()->where('user_id', $d->id)->whereDate('as_of', $asOf)->firstOrFail();
        $this->assertSame('100.00', number_format((float) $dCarry->left_carry, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $dCarry->right_carry, 2, '.', ''));
    }

    public function test_invite_payment_places_id_and_credits_senior_the_same_as_admin_add(): void
    {
        $parent = $this->addMember('Mid Parent', $this->root, 'left');

        $this->get(route('customer.register', [
            'placementID' => $parent->id,
            'position' => 'left',
            'sponsorID' => $parent->id,
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Invite Grandchild',
                'email' => 'invite-grand@test.com',
                'phone' => '111',
                'country' => 'US',
                'sponsor_id' => $parent->id,
                'parent_id' => $parent->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $this->assertNull(User::query()->where('email', 'invite-grand@test.com')->first());
        $this->assertNull(BinaryTree::query()->where('users_id', $parent->id)->value('left_user_id'));

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

        $child = User::query()->where('email', 'invite-grand@test.com')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame($parent->id, $child->sponsor_id);
        $this->assertSame($child->id, BinaryTree::query()->where('users_id', $parent->id)->value('left_user_id'));

        $this->assertSame('100.00', $this->sideTotal($parent->id, 'left'));
        $this->assertSame('200.00', $this->sideTotal($this->root->id, 'left'));
        $this->assertDatabaseHas('binary_tree_lefts', [
            'user_id' => $this->root->id,
            'from_user_id' => $child->id,
            'amount' => '100.00',
        ]);
    }

    public function test_tree_invite_links_omit_sponsor_id_like_reference(): void
    {
        $this->actingAs($this->root)
            ->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('placementID='.$this->root->id, false)
            ->assertSee('position=left', false)
            ->assertDontSee('sponsorID=', false);
    }

    public function test_register_without_sponsor_query_defaults_to_placement(): void
    {
        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
        ]))->assertOk()->assertSee('Sponsor <strong>#'.$this->root->id.'</strong>', false);
    }

    public function test_power_id_guest_pay_activates_existing_id_and_emails_credentials(): void
    {
        $dummy = app(MembershipService::class)->createPowerId($this->root->id, $this->root->id, 'left');

        $this->get(route('customer.register.special', ['target' => encrypt((string) $dummy->id)]))
            ->assertOk()
            ->assertSee('Activate Power ID', false);

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.special.save'), [
                'name' => 'Guest Power',
                'email' => 'guest-power@test.com',
                'phone' => '333',
                'country' => 'US',
                'package_id' => $this->package->id,
            ])
        );

        $this->assertTrue((bool) $dummy->fresh()->is_power_id);
        $this->assertFalse((bool) $dummy->fresh()->is_active);

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

        $activated = $dummy->fresh();
        $this->assertFalse((bool) $activated->is_power_id);
        $this->assertTrue((bool) $activated->is_active);
        $this->assertSame('guest-power@test.com', $activated->email);
        $this->assertSame($dummy->id, BinaryTree::query()->where('users_id', $this->root->id)->value('left_user_id'));
        $this->assertSame('100.00', $this->sideTotal($this->root->id, 'left'));
        Mail::assertSent(\App\Mail\MemberCredentialsMail::class, function ($mail) use ($activated) {
            return $mail->loginId === $activated->id && $mail->hasTo('guest-power@test.com');
        });
    }

    public function test_power_id_activation_credits_upline_volume_like_a_paid_join(): void
    {
        $parent = $this->addMember('Power Parent', $this->root, 'right');

        $dummy = app(MembershipService::class)->createPowerId($parent->id, $this->root->id, 'left');
        $this->assertSame($dummy->id, BinaryTree::query()->where('users_id', $parent->id)->value('left_user_id'));
        $this->assertSame('0.00', $this->sideTotal($parent->id, 'left'));
        $this->assertSame('100.00', $this->sideTotal($this->root->id, 'right'));

        app(MembershipService::class)->activatePowerId($dummy->id, [
            'name' => 'Power Activated',
            'email' => 'power-activated@test.com',
            'package_id' => $this->package->id,
        ]);

        $this->assertSame('100.00', $this->sideTotal($parent->id, 'left'));
        $this->assertSame('200.00', $this->sideTotal($this->root->id, 'right'));
        $this->assertDatabaseHas('binary_tree_rights', [
            'user_id' => $this->root->id,
            'from_user_id' => $dummy->id,
            'amount' => '100.00',
        ]);
    }

    public function test_nowpayments_ipn_join_walks_volume_to_senior(): void
    {
        $parent = $this->addMember('Ipn Parent', $this->root, 'left');

        config([
            'payments.default_receive' => 'nowpayments',
            'payments.nowpayments.api_key' => 'live-key',
            'payments.nowpayments.ipn_secret' => 'ipn-secret',
        ]);

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true], 202),
            '*/invoice' => Http::response([
                'id' => 88880001,
                'invoice_url' => 'https://nowpayments.io/payment/?iid=88880001',
            ], 201),
        ]);

        $this->get(route('customer.register', [
            'placementID' => $parent->id,
            'position' => 'right',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->post(route('customer.register.save'), [
            'name' => 'Ipn Grandchild',
            'email' => 'ipn-grand@test.com',
            'phone' => '222',
            'country' => 'US',
            'sponsor_id' => $this->root->id,
            'parent_id' => $parent->id,
            'position' => 'right',
            'package_id' => $this->package->id,
        ])->assertRedirect('https://nowpayments.io/payment/?iid=88880001');

        $payload = [
            'payment_id' => 555,
            'invoice_id' => 88880001,
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

        $child = User::query()->where('email', 'ipn-grand@test.com')->firstOrFail();
        $this->assertSame($child->id, BinaryTree::query()->where('users_id', $parent->id)->value('right_user_id'));
        $this->assertSame('100.00', $this->sideTotal($parent->id, 'right'));
        $this->assertSame('200.00', $this->sideTotal($this->root->id, 'left'));
        $this->assertDatabaseHas('binary_tree_lefts', [
            'user_id' => $this->root->id,
            'from_user_id' => $child->id,
            'amount' => '100.00',
        ]);
    }

    private function addMember(string $name, User $parent, string $position, ?User $sponsor = null): User
    {
        return app(MembershipService::class)->createActiveMember([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'@tree.test',
            'sponsor_id' => ($sponsor ?? $parent)->id,
            'parent_id' => $parent->id,
            'position' => $position,
            'package_id' => $this->package->id,
        ]);
    }

    private function sideTotal(int $userId, string $side): string
    {
        $model = $side === 'left' ? BinaryTreeLeft::class : BinaryTreeRight::class;

        return number_format((float) $model::query()->where('user_id', $userId)->sum('amount'), 2, '.', '');
    }

    private function binaryPay(int $userId, string $asOf): string
    {
        $row = PaymentDetail::query()
            ->where('user_id', $userId)
            ->whereDate('paid_on', $asOf)
            ->first();

        return number_format((float) ($row->binary_amount ?? 0), 2, '.', '');
    }
}
