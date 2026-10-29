<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class ActivatedAtEstimator
{
    /**
     * Best available first-activation timestamp for an existing customer.
     * Direct joins (expiry ≈ created_at + weekdays) keep created_at including time.
     * Dummy / late-paid IDs use expiry minus MEMBERSHIP_WEEKDAYS (date only).
     * Pass the first renewal previous_expiry so a later renewal does not shift the date.
     */
    public static function estimate(mixed $createdAt, mixed $expiryDate, mixed $firstPreviousExpiry = null, ?int $weekdays = null): ?Carbon
    {
        $originalExpiry = self::asDate($firstPreviousExpiry) ?? self::asDate($expiryDate);
        if ($originalExpiry === null) {
            return null;
        }

        $weekdays ??= (int) config('citymax.membership.weekdays');
        $reversed = $originalExpiry->copy()->startOfDay()->subWeekdays($weekdays);
        $created = self::asTimestamp($createdAt);
        if ($created === null) {
            return $reversed;
        }

        $gap = (int) abs($created->copy()->startOfDay()->diffInDays($reversed->copy()->startOfDay(), false));

        return $gap <= 1 ? $created : $reversed;
    }

    private static function asDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $at = $value instanceof Carbon ? $value->copy() : Carbon::parse((string) $value);

        return $at->startOfDay();
    }

    private static function asTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value->copy() : Carbon::parse((string) $value);
    }
}
