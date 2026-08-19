<?php

namespace Tests\Unit;

use App\Services\Verification\VerificationAnswer;
use App\Support\IncomeCalendar;
use App\Support\VerificationFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerVerificationUnitTest extends TestCase
{
    public function test_filters_whitelist_and_ignore_invalid_dates(): void
    {
        $filters = VerificationFilters::fromRequest(Request::create('/admin/verification', 'GET', [
            'range' => 'custom',
            'focus' => 'binary',
            'days' => 'match',
            'from' => '2026-08-01',
            'to' => '2026-08-10',
        ]));

        $this->assertSame('custom', $filters->range);
        $this->assertSame('binary', $filters->focus);
        $this->assertSame('match', $filters->days);
        $this->assertSame('2026-08-01', $filters->from);
        $this->assertSame('2026-08-10', $filters->to);
        $this->assertFalse($filters->showsSources());
        $this->assertFalse($filters->showsReferrals());

        $invalid = VerificationFilters::fromRequest(Request::create('/admin/verification', 'GET', [
            'range' => 'drop-table',
            'focus' => '<script>',
            'days' => 'nope',
            'from' => '19-08-2026',
            'to' => 'not-a-date',
        ]));

        $this->assertSame('all', $invalid->range);
        $this->assertSame('all', $invalid->focus);
        $this->assertSame('activity', $invalid->days);
        $this->assertNull($invalid->from);
        $this->assertNull($invalid->to);
        $this->assertTrue($invalid->showsSources());
        $this->assertTrue($invalid->showsReferrals());

        $todayIgnoresRange = VerificationFilters::fromRequest(Request::create('/admin/verification', 'GET', [
            'range' => 'today',
            'from' => '2026-08-01',
            'to' => '2026-08-10',
        ]));
        $this->assertSame('today', $todayIgnoresRange->range);
        $this->assertNull($todayIgnoresRange->from);
        $this->assertNull($todayIgnoresRange->to);
        $this->assertSame(['2026-08-19', '2026-08-19'], $todayIgnoresRange->period('2026-08-19', '2026-08-01'));
    }

    public function test_period_resolves_presets_and_swaps_inverted_custom_dates(): void
    {
        $today = '2026-08-19';
        $created = '2026-08-01';

        $all = new VerificationFilters('all', 'all', 'activity', null, null);
        $this->assertSame(['2026-08-01', '2026-08-19'], $all->period($today, $created));

        $todayRange = new VerificationFilters('today', 'all', 'activity', null, null);
        $this->assertSame(['2026-08-19', '2026-08-19'], $todayRange->period($today, $created));

        $yesterday = new VerificationFilters('yesterday', 'all', 'activity', null, null);
        $this->assertSame(['2026-08-18', '2026-08-18'], $yesterday->period($today, $created));

        $week = new VerificationFilters('7d', 'all', 'activity', null, null);
        $this->assertSame(['2026-08-13', '2026-08-19'], $week->period($today, $created));

        $sevenIgnoresDates = VerificationFilters::fromRequest(Request::create('/admin/verification', 'GET', [
            'range' => '7d',
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));
        $this->assertNull($sevenIgnoresDates->from);
        $this->assertNull($sevenIgnoresDates->to);
        $this->assertSame(['2026-08-13', '2026-08-19'], $sevenIgnoresDates->period($today, $created));

        $yesterdayIgnoresDates = VerificationFilters::fromRequest(Request::create('/admin/verification', 'GET', [
            'range' => 'yesterday',
            'from' => '2026-08-01',
            'to' => '2026-08-10',
        ]));
        $this->assertSame(['2026-08-18', '2026-08-18'], $yesterdayIgnoresDates->period($today, $created));

        $customEmpty = new VerificationFilters('custom', 'all', 'activity', null, null);
        $this->assertSame(['2026-08-01', '2026-08-19'], $customEmpty->period($today, $created));

        $custom = new VerificationFilters('custom', 'roi', 'all', '2026-08-10', '2026-08-05');
        $this->assertSame(['2026-08-05', '2026-08-10'], $custom->period($today, $created));
        $this->assertFalse($custom->showsSources());
        $this->assertFalse($custom->showsReferrals());
    }

    public function test_activation_display_includes_time_only_when_a_timestamp_exists(): void
    {
        $stamp = Carbon::parse('2026-08-19 12:00:00', 'UTC');
        $this->assertSame('19 Aug 2026, 8:00 PM', IncomeCalendar::formatWhen($stamp));
        $this->assertSame('12 Aug 2026', IncomeCalendar::formatWhen(null, '2026-08-12'));
        $this->assertSame('19 Aug 2026', IncomeCalendar::formatDate('2026-08-19'));
        $this->assertSame('—', IncomeCalendar::formatWhen(null, null));
    }

    public function test_admin_answers_match_complaint_focus(): void
    {
        $totals = [
            'left' => 750.0,
            'right' => 0.0,
            'matched' => 0.0,
            'roi' => 12.5,
            'binary' => 0.0,
            'referral' => 25.0,
            'match_days' => 0,
        ];

        $ineligible = VerificationAnswer::make('all', 'all', false, true, true, $totals, 5, 10, 250, 50, 0);
        $this->assertSame('warning', $ineligible['tone']);
        $this->assertStringContainsString('not eligible', $ineligible['text']);

        $today = VerificationAnswer::make('all', 'today', true, true, true, $totals, 5, 10, 250, 50, 0);
        $this->assertSame('warning', $today['tone']);
        $this->assertStringContainsString('not been paid yet', $today['text']);
        $this->assertStringContainsString('pays yesterday, not today', $today['text']);

        $missingRun = VerificationAnswer::make('all', 'yesterday', true, false, false, $totals, 5, 10, 250, 50, 0);
        $this->assertStringContainsString('has not been marked completed', $missingRun['text']);

        $roi = VerificationAnswer::make('roi', 'all', true, true, true, $totals, 5, 10, 250, 50, 0);
        $this->assertSame('success', $roi['tone']);
        $this->assertStringContainsString('ROI was paid', $roi['text']);

        $binary = VerificationAnswer::make('binary', 'all', true, true, true, $totals, 5, 10, 250, 50, 0);
        $this->assertStringContainsString('one side is $0', $binary['text']);
        $this->assertStringContainsString('Leftover carry', $binary['text']);

        $business = VerificationAnswer::make('business', 'all', true, true, true, $totals, 5, 10, 250, 50, 0);
        $this->assertStringContainsString('not headcount', $business['text']);

        $referral = VerificationAnswer::make('referral', 'all', true, true, true, $totals, 5, 10, 250, 50, 0);
        $this->assertStringContainsString('Referral paid', $referral['text']);
        $this->assertStringContainsString('sponsor, not placement', $referral['text']);
    }
}
