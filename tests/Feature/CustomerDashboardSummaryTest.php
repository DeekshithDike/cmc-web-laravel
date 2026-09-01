<?php

namespace Tests\Feature;

use App\Enums\WithdrawalStatus;
use App\Models\CarryForward;
use App\Models\PaymentDetail;
use App\Models\Withdrawal;
use App\Support\IncomeCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class CustomerDashboardSummaryTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
    }

    public function test_dashboard_shows_earnings_withdrawals_binary_and_both_invite_links(): void
    {
        $today = IncomeCalendar::today();
        $yesterday = now(IncomeCalendar::timezone())->subDay()->toDateString();

        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.00',
            'binary_amount' => '5.00',
            'referral_amount' => '2.00',
            'total_amount' => '8.00',
            'paid_on' => $yesterday,
        ]);
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.00',
            'binary_amount' => '3.00',
            'referral_amount' => '0.00',
            'total_amount' => '4.00',
            'paid_on' => $today,
        ]);

        Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '50.00',
            'fee' => '2.00',
            'payable_amount' => '48.00',
            'wallet_address' => self::USDT_EVM_ADDRESS,
            'status' => WithdrawalStatus::Completed,
            'meta' => ['network' => 'bep20'],
        ]);
        Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '11.10',
            'fee' => '2.00',
            'payable_amount' => '9.10',
            'wallet_address' => self::USDT_EVM_ADDRESS,
            'status' => WithdrawalStatus::Pending,
            'meta' => ['network' => 'bep20'],
        ]);

        CarryForward::query()->create([
            'user_id' => $this->root->id,
            'left_carry' => '25.00',
            'right_carry' => '0.00',
            'as_of' => $yesterday,
        ]);

        $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Total earned till today', false)
            ->assertSee('$12.00', false)
            ->assertSee('Total withdrawn till today', false)
            ->assertSee('$50.00', false)
            ->assertSee('Pending withdrawal', false)
            ->assertSee('$11.10', false)
            ->assertSee('Withdrawals today', false)
            ->assertSee('Last credited income', false)
            ->assertSee('Today Binary', false)
            ->assertSee('$3.00', false)
            ->assertSee('Binary matching income', false)
            ->assertSee('$8.00', false)
            ->assertSee('Carry leftover left', false)
            ->assertSee('$25.00', false)
            ->assertSee('Share registration link', false)
            ->assertSee('Without sponsor', false)
            ->assertSee('With your sponsor ID', false)
            ->assertDontSee('Quick actions', false);
    }

    public function test_admin_customer_dashboard_shows_the_same_summary_and_invite_links(): void
    {
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '2.50',
            'binary_amount' => '4.00',
            'referral_amount' => '1.00',
            'total_amount' => '7.50',
            'paid_on' => IncomeCalendar::today(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.dashboard', $this->root))
            ->assertOk()
            ->assertSee('Total earned till today', false)
            ->assertSee('$7.50', false)
            ->assertSee('Binary matching income', false)
            ->assertSee('$4.00', false)
            ->assertSee('Share registration link', false)
            ->assertSee('Without sponsor', false)
            ->assertSee('With your sponsor ID', false)
            ->assertSee('id="open-register-url"', false)
            ->assertSee('id="open-register-sponsored-url"', false)
            ->assertDontSee('Request withdrawal', false)
            ->assertDontSee('Quick actions', false);
    }

    public function test_dashboard_does_not_show_another_customers_earnings_or_withdrawals(): void
    {
        $other = $this->addMember('dash-other@citymaxcrypto.com', 'left');

        PaymentDetail::query()->create([
            'user_id' => $other->id,
            'roi_amount' => '99.00',
            'binary_amount' => '88.00',
            'referral_amount' => '0.00',
            'total_amount' => '187.00',
            'paid_on' => IncomeCalendar::today(),
        ]);
        Withdrawal::query()->create([
            'user_id' => $other->id,
            'amount' => '77.00',
            'fee' => '2.00',
            'payable_amount' => '75.00',
            'wallet_address' => self::USDT_EVM_ADDRESS,
            'status' => WithdrawalStatus::Completed,
            'meta' => ['network' => 'bep20'],
        ]);

        $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertDontSee('$187.00', false)
            ->assertDontSee('$99.00', false)
            ->assertDontSee('$88.00', false)
            ->assertDontSee('$77.00', false);
    }
}
