<?php

namespace Tests\Feature;

use App\Models\BinaryIncome;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Models\WalletTransaction;
use App\Services\Income\DailyIncomeService;
use App\Services\Income\RepairMissed16AugIncomeService;
use App\Services\Membership\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class RepairMissed16AugIncomeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCityMaxPlatform;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'citymax.income.binary_percent' => 5,
            'citymax.income.referral_percent' => 10,
            'citymax.income.timezone' => 'Asia/Kuala_Lumpur',
        ]);
    }

    public function test_dry_run_does_not_write_and_apply_backfills_16_aug_then_binary(): void
    {
        $this->seedMissed16Aug();

        $this->artisan('income:repair-16-aug')
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run only');

        $this->assertSame(0, PaymentDetail::query()->whereDate('paid_on', '2026-08-16')->count());
        $this->assertSame(0, DailyIncomeRun::query()->whereDate('as_of', '2026-08-16')->count());

        $rightWallet = (float) $this->user('right-16aug@test.com')->wallet_balance;
        $rightPaid = PaymentDetail::query()
            ->where('user_id', $this->user('right-16aug@test.com')->id)
            ->whereDate('paid_on', '2026-08-17')
            ->first();

        $this->artisan('income:repair-16-aug', ['--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Applied.');

        $run = DailyIncomeRun::query()->whereDate('as_of', '2026-08-16')->first();
        $this->assertNotNull($run);
        $this->assertSame(DailyIncomeRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('cron', $run->triggered_by);
        $this->assertTrue($run->created_at->equalTo(
            Carbon::parse('2026-08-17 00:00:00', 'Asia/Kuala_Lumpur')->utc()
        ));

        $root16 = PaymentDetail::query()
            ->where('user_id', $this->root->id)
            ->whereDate('paid_on', '2026-08-16')
            ->first();
        $this->assertNotNull($root16);
        $this->assertSame('0.00', number_format((float) $root16->roi_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $root16->binary_amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float) $root16->referral_amount, 2, '.', ''));

        $this->assertSame('100.00', number_format((float) CarryForward::query()
            ->where('user_id', $this->root->id)
            ->whereDate('as_of', '2026-08-16')
            ->value('left_carry'), 2, '.', ''));
        $this->assertSame('0.00', number_format((float) CarryForward::query()
            ->where('user_id', $this->root->id)
            ->whereDate('as_of', '2026-08-16')
            ->value('right_carry'), 2, '.', ''));

        $root17 = PaymentDetail::query()
            ->where('user_id', $this->root->id)
            ->whereDate('paid_on', '2026-08-17')
            ->first();
        $this->assertSame('5.00', number_format((float) $root17->binary_amount, 2, '.', ''));
        $this->assertSame('5.00', number_format((float) BinaryIncome::query()
            ->where('user_id', $this->root->id)
            ->whereDate('earned_on', '2026-08-17')
            ->value('amount'), 2, '.', ''));

        $this->assertSame('0.00', number_format((float) CarryForward::query()
            ->where('user_id', $this->root->id)
            ->whereDate('as_of', '2026-08-17')
            ->value('left_carry'), 2, '.', ''));
        $this->assertSame('0.00', number_format((float) CarryForward::query()
            ->where('user_id', $this->root->id)
            ->whereDate('as_of', '2026-08-17')
            ->value('right_carry'), 2, '.', ''));

        $this->assertSame(0, PaymentDetail::query()
            ->where('user_id', $this->user('right-16aug@test.com')->id)
            ->whereDate('paid_on', '2026-08-16')
            ->count());
        $this->assertEquals(
            number_format($rightWallet, 2, '.', ''),
            number_format((float) $this->user('right-16aug@test.com')->fresh()->wallet_balance, 2, '.', '')
        );
        $this->assertSame(
            number_format((float) $rightPaid->total_amount, 2, '.', ''),
            number_format((float) $rightPaid->fresh()->total_amount, 2, '.', '')
        );

        $this->assertTrue(
            WalletTransaction::query()
                ->where('user_id', $this->root->id)
                ->where('reason', 'daily_referral')
                ->where('amount', '10.00')
                ->get()
                ->contains(fn ($tx) => $tx->created_at->equalTo(
                    Carbon::parse('2026-08-17 00:00:00', 'Asia/Kuala_Lumpur')->utc()
                ))
        );

        $walletAfter = (float) $this->root->fresh()->wallet_balance;
        app(RepairMissed16AugIncomeService::class)->apply();
        $this->assertEquals(
            number_format($walletAfter, 2, '.', ''),
            number_format((float) $this->root->fresh()->wallet_balance, 2, '.', '')
        );
        $this->assertSame(1, DailyIncomeRun::query()->whereDate('as_of', '2026-08-16')->count());
        $this->assertSame(1, PaymentDetail::query()->where('user_id', $this->root->id)->whereDate('paid_on', '2026-08-16')->count());
    }

    public function test_partial_binary_is_topped_up_to_expected_and_later_volume_is_included(): void
    {
        $this->seedMissed16Aug();

        $asOf = '2026-08-17';
        $payment = PaymentDetail::query()
            ->where('user_id', $this->root->id)
            ->whereDate('paid_on', $asOf)
            ->firstOrFail();
        $payment->binary_amount = '3.00';
        $payment->total_amount = bcadd((string) $payment->total_amount, '3.00', 2);
        $payment->save();
        BinaryIncome::query()->create([
            'user_id' => $this->root->id,
            'amount' => '3.00',
            'left_volume' => '0.00',
            'right_volume' => '100.00',
            'earned_on' => $asOf,
        ]);

        $left = $this->user('left-16aug@test.com');
        $right = $this->user('right-16aug@test.com');
        \App\Models\BinaryTreeLeft::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $left->id,
            'amount' => '100.00',
            'business_date' => '2026-08-18',
        ]);
        \App\Models\BinaryTreeRight::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $right->id,
            'amount' => '100.00',
            'business_date' => '2026-08-18',
        ]);
        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-18',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'cron',
            'processed' => 1,
            'total_paid' => '0.00',
        ]);
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '0.00',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '0.00',
            'paid_on' => '2026-08-18',
        ]);

        $walletBefore = (float) $this->root->fresh()->wallet_balance;
        app(\App\Services\Wallet\WalletService::class)->debit($this->root->fresh(), 20, 'withdrawal');
        $walletAfterDebit = (float) $this->root->fresh()->wallet_balance;
        $this->assertEquals(number_format($walletBefore - 20, 2, '.', ''), number_format($walletAfterDebit, 2, '.', ''));

        app(RepairMissed16AugIncomeService::class)->apply();

        $this->assertSame('5.00', number_format((float) $payment->fresh()->binary_amount, 2, '.', ''));
        $this->assertSame('5.00', number_format((float) PaymentDetail::query()
            ->where('user_id', $this->root->id)
            ->whereDate('paid_on', '2026-08-18')
            ->value('binary_amount'), 2, '.', ''));
        $this->assertEquals(
            number_format($walletAfterDebit + 10.00 + 2.00 + 5.00, 2, '.', ''),
            number_format((float) $this->root->fresh()->wallet_balance, 2, '.', '')
        );
        $this->assertSame('0.00', number_format((float) CarryForward::query()
            ->where('user_id', $this->root->id)
            ->whereDate('as_of', '2026-08-17')
            ->value('left_carry'), 2, '.', ''));
        $this->assertTrue(
            WalletTransaction::query()
                ->where('user_id', $this->root->id)
                ->where('reason', 'withdrawal')
                ->where('amount', '20.00')
                ->exists()
        );
    }

    public function test_already_paid_binary_above_expected_is_not_clawed_back(): void
    {
        $this->seedMissed16Aug();

        $payment = PaymentDetail::query()
            ->where('user_id', $this->root->id)
            ->whereDate('paid_on', '2026-08-17')
            ->firstOrFail();
        $payment->binary_amount = '10.00';
        $payment->total_amount = bcadd((string) $payment->total_amount, '10.00', 2);
        $payment->save();
        BinaryIncome::query()->create([
            'user_id' => $this->root->id,
            'amount' => '10.00',
            'left_volume' => '0.00',
            'right_volume' => '100.00',
            'earned_on' => '2026-08-17',
        ]);

        $walletBefore = (float) $this->root->fresh()->wallet_balance;
        app(RepairMissed16AugIncomeService::class)->apply();

        $this->assertSame('10.00', number_format((float) $payment->fresh()->binary_amount, 2, '.', ''));
        $this->assertEquals(
            number_format($walletBefore + 10.00, 2, '.', ''),
            number_format((float) $this->root->fresh()->wallet_balance, 2, '.', '')
        );
    }

    private function seedMissed16Aug(): void
    {
        Carbon::setTestNow('2026-08-15 04:00:00');
        $this->createCityMaxPlatform();

        Carbon::setTestNow('2026-08-16 04:00:00');
        app(MembershipService::class)->createActiveMember([
            'name' => 'Left 16 Aug',
            'email' => 'left-16aug@test.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ]);

        Carbon::setTestNow('2026-08-17 04:00:00');
        app(MembershipService::class)->createActiveMember([
            'name' => 'Right 16 Aug',
            'email' => 'right-16aug@test.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
            'package_id' => $this->package->id,
        ]);

        app(DailyIncomeService::class)->run('2026-08-17', 'admin');
        $this->root->refresh();
    }

    private function user(string $email)
    {
        return \App\Models\User::query()->where('email', $email)->firstOrFail();
    }
}
