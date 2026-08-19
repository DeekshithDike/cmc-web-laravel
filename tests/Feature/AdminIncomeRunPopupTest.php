<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DailyIncomeRun;
use App\Models\Package;
use App\Models\User;
use App\Support\IncomeCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminIncomeRunPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_previous_day_income_uses_popup_with_details_not_instant_post(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');

        $admin = $this->makeAdmin();
        $asOf = IncomeCalendar::previousDate();
        $this->assertSame('2026-08-18', $asOf);

        $html = $this->actingAs($admin)
            ->get(route('admin.income.daily'))
            ->assertOk()
            ->assertSee('js-income-run', false)
            ->assertSee('id="incomeRunModal"', false)
            ->assertSee("Run yesterday's income?", false)
            ->assertSee("Yes, run yesterday's income", false)
            ->assertSee('This credits wallets and cannot be undone from this screen', false)
            ->assertSee('18 Aug 2026 (Tuesday)', false)
            ->assertSee('Paid at each package\'s daily percent', false)
            ->assertSee('5% of matched left/right volume', false)
            ->assertSee('10% of that day\'s stored referral package volume', false)
            ->assertSee(route('admin.income.daily.run'), false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/type="button"[^>]*js-income-run|js-income-run[^>]*type="button"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*\/income\/daily\/run"[^>]*>\s*<button[^>]*>Run previous day income/',
            $html,
            'Run previous day income must not POST immediately'
        );
    }

    public function test_weekend_popup_says_roi_is_not_paid(): void
    {
        Carbon::setTestNow('2026-08-16 12:00:00');

        $admin = $this->makeAdmin();
        $this->assertSame('2026-08-15', IncomeCalendar::previousDate());

        $this->actingAs($admin)
            ->get(route('admin.income.daily'))
            ->assertOk()
            ->assertSee('15 Aug 2026 (Saturday)', false)
            ->assertSee('Not paid', false)
            ->assertSee('Saturday is a weekend', false);
    }

    public function test_already_calculated_day_disables_run_and_hides_popup(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');

        $admin = $this->makeAdmin();
        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-18',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'admin',
            'processed' => 3,
            'total_paid' => '12.00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.income.daily'))
            ->assertOk()
            ->assertSee('Already calculated', false)
            ->assertSee('disabled', false)
            ->assertDontSee('js-income-run', false)
            ->assertDontSee('id="incomeRunModal"', false);
    }

    public function test_admin_run_post_still_pays_yesterday_income(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');
        Http::fake(['*' => Http::response(['ok' => true], 202)]);

        $admin = $this->makeAdmin();
        $package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        User::query()->create([
            'name' => 'Income Member',
            'email' => 'income-member@citymax.local',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $package->id,
            'expiry_date' => '2026-12-31',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.income.daily'))
            ->post(route('admin.income.daily.run'))
            ->assertRedirect(route('admin.income.daily'))
            ->assertSessionHas('success');

        $this->assertTrue(
            DailyIncomeRun::query()
                ->whereDate('as_of', '2026-08-18')
                ->where('status', DailyIncomeRun::STATUS_COMPLETED)
                ->where('triggered_by', 'admin')
                ->exists()
        );
    }

    private function makeAdmin(): User
    {
        return User::query()->create([
            'name' => 'Income Admin',
            'email' => 'income-admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);
    }
}
