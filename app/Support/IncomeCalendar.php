<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class IncomeCalendar
{
    public static function timezone(): string
    {
        return (string) config('citymax.income.timezone', 'Asia/Kuala_Lumpur');
    }

    public static function today(?Carbon $now = null): string
    {
        return self::now($now)->toDateString();
    }

    public static function previousDate(?Carbon $now = null): string
    {
        return self::now($now)->subDay()->toDateString();
    }

    /**
     * Inclusive UTC start and exclusive UTC end of a Malaysia calendar day.
     * Use this for created_at / processed_at "today" filters — never UTC whereDate().
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function malaysiaDayUtcBounds(?string $ymd = null, ?Carbon $now = null): array
    {
        $day = $ymd ?: self::today($now);
        $start = Carbon::parse($day, self::timezone())->startOfDay()->utc();

        return [$start, $start->copy()->addDay()];
    }

    public static function constrainToMalaysiaDay(mixed $query, string $column, ?string $ymd = null, ?Carbon $now = null): void
    {
        [$start, $end] = self::malaysiaDayUtcBounds($ymd, $now);
        $query->where($column, '>=', $start)->where($column, '<', $end);
    }

    public static function daysUntil(mixed $date, ?Carbon $now = null): ?int
    {
        if ($date === null || $date === '') {
            return null;
        }

        $ymd = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse((string) $date)->toDateString();
        $today = Carbon::parse(self::today($now), self::timezone())->startOfDay();
        $target = Carbon::parse($ymd, self::timezone())->startOfDay();

        return (int) $today->diffInDays($target, false);
    }

    public static function isExpired(mixed $date, ?Carbon $now = null): bool
    {
        if ($date === null || $date === '') {
            return false;
        }

        $ymd = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse((string) $date)->toDateString();

        return $ymd < self::today($now);
    }

    /**
     * Compact timestamp for tables/CSV: 2026-08-19 20:00 (Malaysia).
     */
    public static function formatDateTime(mixed $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '—';
        }

        $at = $timestamp instanceof Carbon ? $timestamp->copy() : Carbon::parse((string) $timestamp);

        return $at->timezone(self::timezone())->format('Y-m-d H:i');
    }

    /**
     * Admin UI date: 19 Aug 2026. HTML date inputs still use Y-m-d.
     */
    public static function formatDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $ymd = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse((string) $date, self::timezone())->toDateString();

        return Carbon::parse($ymd, self::timezone())->format('j M Y');
    }

    /**
     * Admin UI datetime when a timestamp exists (19 Aug 2026, 11:18 PM); otherwise the date only.
     */
    public static function formatWhen(mixed $timestamp, mixed $dateOnly = null): string
    {
        if ($timestamp) {
            $at = $timestamp instanceof Carbon ? $timestamp->copy() : Carbon::parse((string) $timestamp);

            return $at->timezone(self::timezone())->format('j M Y, g:i A');
        }

        return self::formatDate($dateOnly);
    }

    /**
     * ROI is Monday–Friday Malaysia calendar days.
     */
    public static function paysRoiOn(string $asOf): bool
    {
        $day = Carbon::parse($asOf, self::timezone())->dayOfWeek;

        return $day !== Carbon::SATURDAY && $day !== Carbon::SUNDAY;
    }

    private static function now(?Carbon $now): Carbon
    {
        return $now ? $now->copy()->timezone(self::timezone()) : now(self::timezone());
    }
}
