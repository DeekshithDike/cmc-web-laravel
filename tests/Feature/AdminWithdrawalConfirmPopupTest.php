<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\Withdrawals\WithdrawalService;
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
            ->assertSee('Send via NOWPayments', false)
            ->assertSee('Already paid outside', false)
            ->assertSee('Continue to send via NOWPayments', false)
            ->assertSee('Yes, send via NOWPayments', false)
            ->assertSee('Yes, mark as already paid', false)
            ->assertSee('Yes, decline', false)
            ->assertSee('Final confirmation', false)
            ->assertSee('This cannot be undone from this screen', false)
            ->assertSee('Sends the payable amount to this wallet now.', false)
            ->assertSee('This will not send any crypto', false)
            ->assertSee('the member wallet will not be refunded', false)
            ->assertSee('The full requested amount will be refunded to the member wallet balance', false)
            ->assertSee(route('admin.withdrawals.complete', $pending), false)
            ->assertSee(route('admin.withdrawals.decline', $pending), false)
            ->assertSee('USDT BEP-20', false)
            ->assertDontSee('js-wd-sync', false)
            ->assertDontSee('Sync payout status', false)
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
            ->assertSee('>Remarks<', false)
            ->assertDontSee('js-wd-confirm', false)
            ->assertDontSee('id="wdConfirmModal"', false)
            ->assertDontSee('Pay Now', false)
            ->assertDontSee('>Decline<', false)
            ->assertDontSee('js-wd-sync', false)
            ->assertDontSee('Sync payout status', false);
    }

    public function test_all_withdrawal_lists_show_remarks(): void
    {
        [$admin, $user] = $this->seedAdminAndMember();

        $rows = [
            WithdrawalStatus::Pending->value => 'pending remarks note',
            WithdrawalStatus::Processing->value => 'processing remarks note',
            WithdrawalStatus::Completed->value => 'completed remarks note',
            WithdrawalStatus::Declined->value => 'declined remarks note',
        ];

        foreach ($rows as $status => $remarks) {
            Withdrawal::query()->create([
                'user_id' => $user->id,
                'amount' => '25.00',
                'fee' => '2.00',
                'payable_amount' => '23.00',
                'wallet_address' => self::USDT_EVM_ADDRESS,
                'status' => $status,
                'remarks' => $remarks,
            ]);
        }

        foreach ($rows as $status => $remarks) {
            $this->actingAs($admin)
                ->get(route('admin.withdrawals.index', $status))
                ->assertOk()
                ->assertSee('>Remarks<', false)
                ->assertSee($remarks, false);
        }
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

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', 'completed'))
            ->assertOk()
            ->assertSee('paid in popup test', false);

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', 'declined'))
            ->assertOk()
            ->assertSee('declined in popup test', false);
    }

    public function test_manual_mark_as_paid_completes_without_refund(): void
    {
        [$admin, $user] = $this->seedAdminAndMember();
        $before = (float) $user->wallet_balance;

        app(WithdrawalService::class)->request($user, 25, self::USDT_EVM_ADDRESS);
        $wd = Withdrawal::query()->latest('id')->firstOrFail();
        $afterDebit = (float) $user->fresh()->wallet_balance;

        $this->assertSame(WithdrawalStatus::Pending, $wd->status);
        $this->assertEqualsWithDelta($before - 25, $afterDebit, 0.01);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'debit',
            'reason' => 'withdrawal_request',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.withdrawals.index', 'pending'))
            ->post(route('admin.withdrawals.complete', $wd), [
                'payout_method' => 'manual',
                'remarks' => 'should be ignored',
            ])
            ->assertRedirect(route('admin.withdrawals.index', 'pending'))
            ->assertSessionHas('success');

        $fresh = $wd->fresh();
        $this->assertSame(WithdrawalStatus::Completed, $fresh->status);
        $this->assertSame(PaymentProvider::Manual->value, $fresh->payout_provider);
        $this->assertSame('Admin manually paid', $fresh->remarks);
        $this->assertEqualsWithDelta($afterDebit, (float) $user->fresh()->wallet_balance, 0.01);

        $this->assertSame(0, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('reason', ['withdrawal_declined', 'withdrawal_payout_failed'])
            ->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $user->id)->count());

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', 'completed'))
            ->assertOk()
            ->assertSee('Admin manually paid', false)
            ->assertSee('manual', false);
    }

    public function test_processing_tab_shows_sync_popup_when_rows_exist(): void
    {
        [$admin, $user] = $this->seedAdminAndMember();
        Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '2.00',
            'payable_amount' => '23.00',
            'wallet_address' => self::USDT_EVM_ADDRESS,
            'status' => WithdrawalStatus::Processing,
            'payout_provider' => 'nowpayments',
            'payout_ref' => 'wd-sync-1',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', 'processing'))
            ->assertOk()
            ->assertSee('js-wd-sync', false)
            ->assertSee('id="wdSyncModal"', false)
            ->assertSee('Sync payout status?', false)
            ->assertSee('Yes, sync status', false)
            ->assertSee('does <strong>not</strong> send money again', false)
            ->assertSee('full amount is refunded', false)
            ->assertSee(route('admin.withdrawals.sync-processing'), false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*\/withdrawals\/sync-processing"[^>]*>\s*<button[^>]*>Sync payout status/',
            $html,
            'Sync payout status must not POST immediately'
        );
    }

    public function test_processing_tab_hides_sync_when_empty(): void
    {
        [$admin] = $this->seedAdminAndMember();

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index', 'processing'))
            ->assertOk()
            ->assertDontSee('js-wd-sync', false)
            ->assertDontSee('id="wdSyncModal"', false)
            ->assertDontSee('Sync payout status', false);
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
