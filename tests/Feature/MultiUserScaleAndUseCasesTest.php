<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\DailyIncomeRun;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\PaymentTransaction;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Income\DailyIncomeService;
use App\Services\Membership\MembershipService;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Scale + full use-case coverage with ~100 active members under the root tree.
 */
class MultiUserScaleAndUseCasesTest extends TestCase
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
            'payments.default_payout' => 'manual',
            'payments.nowpayments.api_key' => null,
            'citymax.income.binary_percent' => 5,
            'citymax.income.referral_percent' => 10,
        ]);

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Scale Admin',
            'email' => 'scale-admin@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'Scale Root',
            'email' => 'scale-root@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'wallet_balance' => '50.00',
        ]);

        BinaryTree::query()->create(['users_id' => $this->root->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_one_hundred_members_tree_income_withdrawal_and_idempotent_daily_run(): void
    {
        $membership = app(MembershipService::class);
        $parents = [$this->root];
        $created = [];

        // Build a balanced-ish binary tree of 100 paid members under root.
        for ($i = 1; $i <= 100; $i++) {
            $parent = $parents[(int) floor(($i - 1) / 2)];
            $position = ($i % 2 === 1) ? 'left' : 'right';

            // Prefer free seat; if parent side taken, walk to next free parent in queue.
            $placed = null;
            foreach ($parents as $candidate) {
                $tree = BinaryTree::query()->where('users_id', $candidate->id)->first();
                if ($position === 'left' && $tree && ! $tree->left_user_id) {
                    $placed = $membership->createActiveMember([
                        'name' => "Member {$i}",
                        'email' => "member{$i}@scale.test",
                        'sponsor_id' => $this->root->id,
                        'parent_id' => $candidate->id,
                        'position' => 'left',
                        'package_id' => $this->package->id,
                    ]);
                    break;
                }
                if ($position === 'right' && $tree && ! $tree->right_user_id) {
                    $placed = $membership->createActiveMember([
                        'name' => "Member {$i}",
                        'email' => "member{$i}@scale.test",
                        'sponsor_id' => $this->root->id,
                        'parent_id' => $candidate->id,
                        'position' => 'right',
                        'package_id' => $this->package->id,
                    ]);
                    break;
                }
            }

            if (! $placed) {
                foreach ($parents as $candidate) {
                    $tree = BinaryTree::query()->where('users_id', $candidate->id)->first();
                    if ($tree && ! $tree->left_user_id) {
                        $placed = $membership->createActiveMember([
                            'name' => "Member {$i}",
                            'email' => "member{$i}@scale.test",
                            'sponsor_id' => $this->root->id,
                            'parent_id' => $candidate->id,
                            'position' => 'left',
                            'package_id' => $this->package->id,
                        ]);
                        break;
                    }
                    if ($tree && ! $tree->right_user_id) {
                        $placed = $membership->createActiveMember([
                            'name' => "Member {$i}",
                            'email' => "member{$i}@scale.test",
                            'sponsor_id' => $this->root->id,
                            'parent_id' => $candidate->id,
                            'position' => 'right',
                            'package_id' => $this->package->id,
                        ]);
                        break;
                    }
                }
            }

            $this->assertNotNull($placed, "Failed to place member {$i}");
            $created[] = $placed;
            $parents[] = $placed;
        }

        $this->assertSame(100, count($created));
        $this->assertSame(101, User::query()->where('role', UserRole::Customer)->where('is_active', true)->count());
        $this->assertSame(100, ReferralIncome::query()->where('user_id', $this->root->id)->count());
        $this->assertEquals(
            '10000.00',
            number_format((float) ReferralIncome::query()->where('user_id', $this->root->id)->sum('amount'), 2, '.', '')
        );

        // Dashboard shows 10% of stored referral volume (not raw 10000).
        $this->actingAs($this->root)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Today Referral', false)
            ->assertSee('1000.00', false);

        $asOf = now()->toDateString();
        $result = app(DailyIncomeService::class)->run($asOf, 'admin');
        $this->assertFalse($result['skipped']);
        $this->assertGreaterThanOrEqual(100, $result['processed']);

        $rootPay = PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->firstOrFail();
        $this->assertSame('1.00', number_format((float) $rootPay->roi_amount, 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $rootPay->referral_amount, 2, '.', ''));
        // Binary capped at package ($100) even with huge matched volume.
        $this->assertSame('100.00', number_format((float) $rootPay->binary_amount, 2, '.', ''));

        $again = app(DailyIncomeService::class)->run($asOf, 'cron');
        $this->assertTrue($again['skipped']);
        $this->assertSame(1, DailyIncomeRun::query()->whereDate('as_of', $asOf)->where('status', 'completed')->count());
        $this->assertSame(1, PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', $asOf)->count());

        // Cron path (yesterday) is also idempotent when admin already ran that day.
        Carbon::setTestNow('2026-08-15 00:10:00');
        $y = app(DailyIncomeService::class)->run(null, 'cron');
        $this->assertSame('2026-08-14', $y['asOf']);
        $this->assertTrue($y['skipped']);

        Carbon::setTestNow('2026-08-14 12:00:00');

        // Customer pages still load for a deep member.
        $deep = $created[99];
        $this->actingAs($deep)->get(route('customer.dashboard'))->assertOk();
        $this->actingAs($deep)->get(route('customer.tree'))->assertOk()->assertDontSee('sponsorID=', false);
        $this->actingAs($deep)->get(route('customer.income.history'))->assertOk();

        // Withdrawal path with ERC-20 address.
        $leaf = $created[0]->fresh();
        $leaf->update(['wallet_balance' => '80.00']);
        $withdrawal = app(WithdrawalService::class)->request($leaf, 30, self::USDT_EVM_ADDRESS);
        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);

        $this->actingAs($this->admin)->post(route('admin.withdrawals.complete', $withdrawal))->assertRedirect();
        $this->assertSame(WithdrawalStatus::Completed, $withdrawal->fresh()->status);

        // Admin list + income pages under load.
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->assertSee('Member 1', false);
        $this->actingAs($this->admin)->get(route('admin.income.daily'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.business.all'))->assertOk();
    }

    public function test_invite_pay_confirm_and_power_id_guest_flow_still_work_after_scale_helpers(): void
    {
        $membership = app(MembershipService::class);
        $left = $membership->createActiveMember([
            'name' => 'Left Anchor',
            'email' => 'left-anchor@scale.test',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ]);

        $this->get(route('customer.register', [
            'placementID' => $left->id,
            'position' => 'left',
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Invite Child',
                'email' => 'invite-child@scale.test',
                'phone' => '111',
                'country' => 'US',
                'sponsor_id' => $left->id,
                'parent_id' => $left->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'invite-child@scale.test', 'is_active' => true]);

        $dummy = $membership->createPowerId($this->root->id, $this->root->id, 'right');
        $this->get(route('customer.register.special', ['target' => encrypt((string) $dummy->id)]))
            ->assertOk()
            ->assertSee('Activate Power ID', false);

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.special.save'), [
                'name' => 'Power Guest',
                'email' => 'power-guest@scale.test',
                'phone' => '222',
                'country' => 'US',
                'package_id' => $this->package->id,
            ])
        );

        $powerTx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $powerTx))->assertRedirect()->assertSessionHas('success');
        $this->assertFalse((bool) $dummy->fresh()->is_power_id);
        $this->assertTrue((bool) $dummy->fresh()->is_active);
        $this->assertSame('power-guest@scale.test', $dummy->fresh()->email);
    }
}
