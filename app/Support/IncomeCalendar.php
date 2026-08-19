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
