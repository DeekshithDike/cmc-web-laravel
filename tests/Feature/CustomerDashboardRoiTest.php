<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Support\IncomeCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class CustomerDashboardRoiTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
    }

    public function test_dashboard_shows_zero_roi_wallet_when_none_earned(): void
    {
        $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('ROI wallet income', false)
            ->assertSee('0 days paid', false)
            ->assertSee('$0.00', false)
            ->assertSee('Last paid ROI', false)
            ->assertSee('No ROI credited yet', false)
            ->assertSee('Total ROI', false)
            ->assertSee('All ROI credited so far', false)
            ->assertSee('Paid days', false)
            ->assertSee('Weekdays ROI was paid', false);
    }

    public function test_dashboard_shows_last_paid_roi_total_and_days_for_this_customer_only(): void
    {
        $yesterday = now(IncomeCalendar::timezone())->subDay()->toDateString();
        $twoDaysAgo = now(IncomeCalendar::timezone())->subDays(2)->toDateString();
        $threeDaysAgo = now(IncomeCalendar::timezone())->subDays(3)->toDateString();

        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.25',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.25',
            'paid_on' => $twoDaysAgo,
        ]);
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.00',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.00',
            'paid_on' => $threeDaysAgo,
        ]);
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '0.00',
            'binary_amount' => '5.00',
            'referral_amount' => '0.00',
            'total_amount' => '5.00',
            'paid_on' => $yesterday,
        ]);

        $other = User::query()->create([
            'name' => 'Other',
            'email' => 'other-roi@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        PaymentDetail::query()->create([
            'user_id' => $other->id,
            'roi_amount' => '99.00',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '99.00',
            'paid_on' => $yesterday,
        ]);

        $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('ROI wallet income', false)
            ->assertSee('Last paid ROI', false)
            ->assertSee('Paid days', false)
            ->assertSee('2 days paid', false)
            ->assertSee('$1.25', false)
            ->assertSee('Credited on '.IncomeCalendar::formatDate($twoDaysAgo), false)
            ->assertSee('$2.25', false)
            ->assertDontSee('$99.00', false)
            ->assertDontSee('No ROI credited yet', false);
    }
}
