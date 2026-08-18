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
