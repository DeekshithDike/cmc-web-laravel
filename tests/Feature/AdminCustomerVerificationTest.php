<?php

namespace Tests\Feature;

use App\Models\BinaryIncome;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Services\Income\DailyIncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class AdminCustomerVerificationTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-19 12:00:00');
        $this->createCityMaxPlatform();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_and_customer_cannot_open_verification(): void
    {
        $this->get(route('admin.verification.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($this->root)
            ->get(route('admin.verification.index', ['q' => $this->root->id]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_empty_lookup_and_menu_and_user_verify_link(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.verification.index'))
            ->assertOk()
            ->assertSee('Customer verification', false)
            ->assertSee('Customer ID (required)', false)
            ->assertDontSee('Period totals', false);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Customer Verification', false)
            ->assertSee('> Verify</a>', false)
            ->getContent();

        $this->assertStringContainsString(route('admin.verification.index', ['q' => $this->root->id], false), $html);
        $this->assertStringContainsString(route('admin.users.edit', $this->root, false), $html);
    }

    public function test_lookup_by_id_shows_income_business_match_and_referral_picture(): void
    {
        $left = $this->addMember('verify-left@citymaxcrypto.com', 'left', extra: ['name' => 'Left Child']);
        $right = $this->addMember('verify-right@citymaxcrypto.com', 'right', extra: ['name' => 'Right Child']);

        app(DailyIncomeService::class)->run('2026-08-18', 'cron');

        $html = $this->actingAs($this->admin)
            ->get(route('admin.verification.index', ['q' => (string) $this->root->id]))
            ->assertOk()
            ->assertSee('Customer #'.$this->root->id, false)
            ->assertSee('Eligible to be paid', false)
            ->assertSee('Activated date', false)
            ->assertSee(\App\Support\IncomeCalendar::formatWhen($this->root->fresh()->created_at), false)
            ->assertDontSee('Today Malaysia', false)
            ->assertSee('Period totals', false)
            ->assertSee('Day-wise ledger', false)
            ->assertDontSee('Why this day', false)
            ->assertSee('Left / Right breakdown', false)
            ->assertSee('Referral breakdown', false)
            ->assertSee('Left Child', false)
            ->assertSee('Right Child', false)
            ->assertSee('#'.$left->id, false)
            ->assertSee('#'.$right->id, false)
            ->getContent();

        $this->assertStringContainsString('ROI paid', $html);
        $this->assertStringContainsString('Binary paid', $html);
        $this->assertStringContainsString('Matched business', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_today_filter_and_complaint_focus_change_what_admin_sees(): void
    {
        $this->addMember('focus-left@citymaxcrypto.com', 'left');
        app(DailyIncomeService::class)->run('2026-08-18', 'cron');

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'today',
                'focus' => 'roi',
            ]))
            ->assertOk()
            ->assertSee('Today is not paid yet', false)
            ->assertDontSee('Left / Right breakdown', false)
            ->assertDontSee('Referral breakdown', false);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'yesterday',
                'focus' => 'binary',
                'days' => 'match',
            ]))
            ->assertOk()
            ->assertSee('Binary paid', false)
            ->assertDontSee('Left / Right breakdown', false);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'focus' => 'business',
            ]))
            ->assertOk()
            ->assertSee('Left / Right breakdown', false)
            ->assertDontSee('Referral breakdown', false);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'focus' => 'referral',
            ]))
            ->assertOk()
            ->assertSee('Referral breakdown', false)
            ->assertDontSee('Left / Right breakdown', false);
    }

    public function test_one_sided_volume_is_not_treated_as_a_match(): void
    {
        BinaryTreeLeft::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '200.00',
            'business_date' => '2026-08-12',
        ]);
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.00',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.00',
            'paid_on' => '2026-08-12',
        ]);
        CarryForward::query()->create([
            'user_id' => $this->root->id,
            'left_carry' => '200.00',
            'right_carry' => '0.00',
            'as_of' => '2026-08-12',
        ]);
        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-18',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'cron',
            'processed' => 1,
            'total_paid' => '1.00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'custom',
                'from' => '2026-08-12',
                'to' => '2026-08-12',
                'focus' => 'binary',
                'days' => 'activity',
            ]))
            ->assertOk()
            ->assertSee('$200.00', false)
            ->assertSee('one side is $0', false);
    }

    public function test_weekend_roi_is_zero_and_match_still_shows(): void
    {
        BinaryTreeLeft::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '100.00',
            'business_date' => '2026-08-15',
        ]);
        BinaryTreeRight::query()->create([
            'user_id' => $this->root->id,
            'from_user_id' => $this->root->id,
            'amount' => '100.00',
            'business_date' => '2026-08-15',
        ]);
        BinaryIncome::query()->create([
            'user_id' => $this->root->id,
            'amount' => '5.00',
            'left_volume' => '100.00',
            'right_volume' => '100.00',
            'earned_on' => '2026-08-15',
        ]);
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '0.00',
            'binary_amount' => '5.00',
            'referral_amount' => '0.00',
            'total_amount' => '5.00',
            'paid_on' => '2026-08-15',
        ]);
        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-18',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'cron',
            'processed' => 1,
            'total_paid' => '5.00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'custom',
                'from' => '2026-08-15',
                'to' => '2026-08-15',
                'focus' => 'roi',
                'days' => 'activity',
            ]))
            ->assertOk()
            ->assertSee('$5.00', false)
            ->assertSee('Weekends are skipped', false);
    }

    public function test_multiple_name_matches_show_picker_and_admin_id_is_not_a_customer(): void
    {
        User::query()->create([
            'name' => 'Verify Twin One',
            'email' => 'twin-one@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => $this->root->role,
            'status' => $this->root->status,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        User::query()->create([
            'name' => 'Verify Twin Two',
            'email' => 'twin-two@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => $this->root->role,
            'status' => $this->root->status,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', ['q' => 'Verify Twin']))
            ->assertOk()
            ->assertSee('Select a customer', false)
            ->assertSee('Verify Twin One', false)
            ->assertSee('Verify Twin Two', false)
            ->assertDontSee('Period totals', false);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', ['q' => (string) $this->admin->id]))
            ->assertOk()
            ->assertSee('No customer matched', false)
            ->assertDontSee('Period totals', false);
    }

    public function test_other_customer_income_is_not_shown_and_names_are_escaped(): void
    {
        $other = User::query()->create([
            'name' => 'Other Member',
            'email' => 'other-verify@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => $this->root->role,
            'status' => $this->root->status,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        PaymentDetail::query()->create([
            'user_id' => $other->id,
            'roi_amount' => '99.00',
            'binary_amount' => '88.00',
            'referral_amount' => '77.00',
            'total_amount' => '264.00',
            'paid_on' => '2026-08-18',
        ]);

        $child = $this->addMember('xss-verify@citymaxcrypto.com', 'left', extra: [
            'name' => '<script>alert(1)</script>',
        ]);
        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-18',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'cron',
            'processed' => 1,
            'total_paid' => '1.00',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.verification.index', ['q' => $this->root->id]))
            ->assertOk()
            ->assertDontSee('$99.00', false)
            ->assertDontSee('$88.00', false)
            ->assertDontSee('$77.00', false)
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('#'.$child->id, $html);
    }

    public function test_ledger_and_sources_are_paginated(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $day = Carbon::parse('2026-08-01')->addDays($i - 1)->toDateString();
            BinaryTreeLeft::query()->create([
                'user_id' => $this->root->id,
                'from_user_id' => $this->root->id,
                'amount' => '10.00',
                'business_date' => $day,
            ]);
            PaymentDetail::query()->create([
                'user_id' => $this->root->id,
                'roi_amount' => '1.00',
                'binary_amount' => '0.00',
                'referral_amount' => '0.00',
                'total_amount' => '1.00',
                'paid_on' => $day,
            ]);
        }

        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-18',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'cron',
            'processed' => 1,
            'total_paid' => '1.00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-12',
                'days' => 'activity',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSee('Showing 1–10 of 12', false)
            ->assertSee('12 Aug 2026 Wed', false)
            ->assertDontSee('1 Aug 2026 Sat', false);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-12',
                'days' => 'activity',
                'per_page' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('Showing 11–12 of 12', false)
            ->assertSee('1 Aug 2026 Sat', false);

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-12',
                'focus' => 'business',
                'per_page' => 10,
                'sources_page' => 2,
            ]))
            ->assertOk()
            ->assertSee('Left / Right breakdown', false)
            ->assertSee('Showing 11–12 of 12', false);
    }

    public function test_verification_query_count_stays_bounded(): void
    {
        $this->addMember('perf-left@citymaxcrypto.com', 'left');
        $this->addMember('perf-right@citymaxcrypto.com', 'right');
        app(DailyIncomeService::class)->run('2026-08-18', 'cron');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', ['q' => $this->root->id]))
            ->assertOk();

        $this->assertLessThan(30, count(DB::getQueryLog()));
    }

    public function test_invalid_filters_do_not_break_the_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.verification.index', [
                'q' => $this->root->id,
                'range' => 'hack',
                'focus' => 'hack',
                'days' => 'hack',
                'from' => 'nope',
                'to' => '2026-99-99',
                'per_page' => 9999,
            ]))
            ->assertOk()
            ->assertSee('Customer #'.$this->root->id, false);
    }
}
