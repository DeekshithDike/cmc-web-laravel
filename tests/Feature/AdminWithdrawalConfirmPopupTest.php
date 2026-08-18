<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWithdrawalConfirmPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_pay_and_decline_use_popup_not_instant_post(): void
    {
        [$admin, $user] = $this->seedAdminAndMember();
        $pending = $this->pendingWithdrawal($user, '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $done = Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '2.00',
            'payable_amount' => '23.00',
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
            'status' => WithdrawalStatus::Completed,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', 'pending'))
            ->assertOk()
            ->assertSee('js-wd-confirm', false)
            ->assertSee('id="wdConfirmModal"', false)
            ->assertSee('Pay this withdrawal?', false)
            ->assertSee('Decline this withdrawal?', false)
            ->assertSee('Yes, pay now', false)
            ->assertSee('Yes, decline', false)
            ->assertSee('This cannot be undone from this screen', false)
            ->assertSee('The payable amount will be sent to the wallet address shown below', false)
            ->assertSee('refunded to the member wallet balance', false)
            ->assertSee(route('admin.withdrawals.complete', $pending), false)
            ->assertSee(route('admin.withdrawals.decline', $pending), false)
            ->assertSee('USDT BEP-20', false)
            ->getContent();

        $this->assertSame(2, preg_match_all('/type="button"[^>]*js-wd-confirm|js-wd-confirm[^>]*type="button"/', $html));
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*\/withdrawals\/\d+\/(complete|decline)"/',
            $html,
            'Pay Now / Decline must not POST immediately from a row form'
        );
        $this->assertStringNotContainsString(
            route('admin.withdrawals.complete', $done),
            $html
        );
    }

    public function test_non_pending_tabs_have_no_pay_or_decline_actions(): void
    {
        [$admin, $user] = $this->seedAdminAndMember();
        Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '2.00',
            'payable_amount' => '23.00',
            'wallet_address' => self::USDT_EVM_ADDRESS,
            'status' => WithdrawalStatus::Completed,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', 'completed'))
            ->assertOk()
            ->assertDontSee('js-wd-confirm', false)
            ->assertDontSee('id="wdConfirmModal"', false)
            ->assertDontSee('Pay Now', false)
            ->assertDontSee('>Decline<', false);
    }

    public function test_admin_pay_and_decline_posts_still_work(): void
    {
        [$admin, $user] = $this->seedAdminAndMember();
        $pay = $this->pendingWithdrawal($user, self::USDT_EVM_ADDRESS);
        $decline = $this->pendingWithdrawal($user, 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS');

        $this->actingAs($admin)
            ->from(route('admin.withdrawals.index', 'pending'))
            ->post(route('admin.withdrawals.complete', $pay), ['remarks' => 'paid in popup test'])
            ->assertRedirect(route('admin.withdrawals.index', 'pending'))
            ->assertSessionHas('success');
        $this->assertSame(WithdrawalStatus::Completed, $pay->fresh()->status);

        $this->actingAs($admin)
            ->from(route('admin.withdrawals.index', 'pending'))
            ->post(route('admin.withdrawals.decline', $decline), ['remarks' => 'declined in popup test'])
            ->assertRedirect(route('admin.withdrawals.index', 'pending'))
            ->assertSessionHas('success');
        $this->assertSame(WithdrawalStatus::Declined, $decline->fresh()->status);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function seedAdminAndMember(): array
    {
        $admin = User::query()->create([
            'name' => 'WD Admin',
            'email' => 'wd-admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $user = User::query()->create([
            'name' => 'WD User',
            'email' => 'wd-user@citymax.local',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'wallet_balance' => '100.00',
        ]);

        return [$admin, $user];
    }

    private function pendingWithdrawal(User $user, string $wallet): Withdrawal
    {
        return Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '2.00',
            'payable_amount' => '23.00',
            'wallet_address' => $wallet,
            'status' => WithdrawalStatus::Pending,
            'meta' => ['network' => str_starts_with($wallet, '0x') ? 'bep20' : 'trc20'],
        ]);
    }
}
