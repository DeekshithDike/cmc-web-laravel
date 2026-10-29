<?php

namespace Tests\Unit;

use App\Support\ActivatedAtEstimator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivatedAtEstimatorTest extends TestCase
{
    public function test_direct_join_keeps_created_at_including_time(): void
    {
        $created = Carbon::parse('2026-03-16 14:30:00', 'UTC');
        $expiry = $created->copy()->addWeekdays(200);

        $estimated = ActivatedAtEstimator::estimate($created, $expiry);

        $this->assertNotNull($estimated);
        $this->assertTrue($created->equalTo($estimated));
    }

    public function test_one_day_gap_still_uses_created_at(): void
    {
        $created = Carbon::parse('2026-03-16 23:00:00', 'UTC');
        $expiry = $created->copy()->addDay()->addWeekdays(200);

        $estimated = ActivatedAtEstimator::estimate($created, $expiry);

        $this->assertNotNull($estimated);
        $this->assertTrue($created->equalTo($estimated));
    }

    public function test_dummy_later_paid_uses_reversed_expiry_date(): void
    {
        $created = Carbon::parse('2026-01-05 10:00:00', 'UTC');
        $activated = Carbon::parse('2026-03-16 09:15:00', 'UTC');
        $expiry = $activated->copy()->addWeekdays(200);

        $estimated = ActivatedAtEstimator::estimate($created, $expiry);

        $this->assertNotNull($estimated);
        $this->assertSame($activated->toDateString(), $estimated->toDateString());
        $this->assertSame('00:00:00', $estimated->format('H:i:s'));
        $this->assertFalse($created->equalTo($estimated));
    }

    public function test_renewed_direct_join_uses_first_previous_expiry_not_current(): void
    {
        $created = Carbon::parse('2026-03-16 14:30:00', 'UTC');
        $firstExpiry = $created->copy()->addWeekdays(200);
        $currentExpiry = $firstExpiry->copy()->addDay()->addWeekdays(200);

        $withoutHistory = ActivatedAtEstimator::estimate($created, $currentExpiry);
        $withHistory = ActivatedAtEstimator::estimate($created, $currentExpiry, $firstExpiry);

        $this->assertNotNull($withoutHistory);
        $this->assertNotSame($created->toDateString(), $withoutHistory->toDateString());
        $this->assertNotNull($withHistory);
        $this->assertTrue($created->equalTo($withHistory));
    }

    public function test_renewed_dummy_uses_reversed_first_expiry(): void
    {
        $created = Carbon::parse('2026-01-05 10:00:00', 'UTC');
        $firstActivate = Carbon::parse('2026-03-16 09:15:00', 'UTC');
        $firstExpiry = $firstActivate->copy()->addWeekdays(200);
        $currentExpiry = $firstExpiry->copy()->addDay()->addWeekdays(200);

        $estimated = ActivatedAtEstimator::estimate($created, $currentExpiry, $firstExpiry);

        $this->assertNotNull($estimated);
        $this->assertSame($firstActivate->toDateString(), $estimated->toDateString());
    }

    public function test_missing_expiry_returns_null(): void
    {
        $this->assertNull(ActivatedAtEstimator::estimate(now(), null));
    }
}
