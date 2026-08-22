<?php

namespace Tests\Feature;

use App\Enums\WithdrawalStatus;
use App\Models\PaymentDetail;
use App\Models\Withdrawal;
use App\Support\IncomeCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class MalaysiaDisplayConsistencyTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCityMaxPlatform();
    }

    public function test_admin_today_stats_use_malaysia_day_not_utc_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16 16:24:46', 'UTC'));

        $this->root->forceFill(['created_at' => Carbon::parse('2026-08-16 08:00:00', 'UTC')])->save();

        $todayJoin = $this->addMember('my-today@citymaxcrypto.com', 'left');
        $todayJoin->forceFill(['created_at' => Carbon::parse('2026-08-16 16:24:46', 'UTC')])->save();

        Withdrawal::query()->create([
            'user_id' => $todayJoin->id,
            'amount' => '40.00',
            'fee' => '2.00',
            'payable_amount' => '38.00',
            'wallet_address' => '0xmalaysiaTodayWallet1',
            'status' => WithdrawalStatus::Completed,
            'processed_at' => Carbon::parse('2026-08-16 16:30:00', 'UTC'),
        ]);
        Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '15.00',
            'fee' => '2.00',
            'payable_amount' => '13.00',
            'wallet_address' => '0xmalaysiaYesterdayWal',
            'status' => WithdrawalStatus::Completed,
            'processed_at' => Carbon::parse('2026-08-16 08:00:00', 'UTC'),
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame('2026-08-17', IncomeCalendar::today());
        $this->assertStringContainsString("Today&#039;s Users", $html);
        $this->assertStringContainsString('<h1 class="no-margins">1</h1>', $html);
        $this->assertStringContainsString("Today&#039;s Withdrawal", $html);
        $this->assertStringContainsString('<h1 class="no-margins">$40.00</h1>', $html);

        Carbon::setTestNow();
    }

    public function test_customer_income_and_withdrawal_history_show_malaysia_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'UTC'));

        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.25',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.25',
            'paid_on' => '2026-08-18',
        ]);

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '25.00',
            'fee' => '2.00',
            'payable_amount' => '23.00',
            'wallet_address' => '0xmalaysiaHistoryWall',
            'status' => WithdrawalStatus::Completed,
            'created_at' => Carbon::parse('2026-08-16 16:24:46', 'UTC'),
            'updated_at' => Carbon::parse('2026-08-16 16:24:46', 'UTC'),
        ]);

        $this->actingAs($this->root)
            ->get(route('customer.income.history'))
            ->assertOk()
            ->assertSee(IncomeCalendar::formatDate('2026-08-18'), false)
            ->assertDontSee('2026-08-18', false);

        $this->actingAs($this->root)
            ->get(route('customer.withdrawals.history'))
            ->assertOk()
            ->assertSee(IncomeCalendar::formatWhen($withdrawal->created_at), false)
            ->assertDontSee('2026-08-16 16:24', false);

        $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee(IncomeCalendar::formatDate($this->root->expiry_date), false)
            ->assertSee('Server time · Malaysia', false);

        Carbon::setTestNow();
    }

    public function test_csv_export_timestamps_are_malaysia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'UTC'));
        $this->root->forceFill(['created_at' => Carbon::parse('2026-08-19 12:00:00', 'UTC')])->save();

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.users.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString(IncomeCalendar::formatDateTime($this->root->created_at), $csv);
        $this->assertStringNotContainsString('2026-08-19 12:00', $csv);

        Carbon::setTestNow();
    }
}
