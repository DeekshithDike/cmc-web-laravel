<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class VerificationFilters
{
    public const RANGES = ['all', 'today', 'yesterday', '7d', 'custom'];

    public const FOCUSES = ['all', 'roi', 'binary', 'business', 'referral'];

    public const DAYS = ['all', 'activity', 'match', 'zero'];

    public function __construct(
        public readonly string $range,
        public readonly string $focus,
        public readonly string $days,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::pick($request->query('range'), self::RANGES, 'all'),
            self::pick($request->query('focus'), self::FOCUSES, 'all'),
            self::pick($request->query('days'), self::DAYS, 'activity'),
            self::dateOrNull($request->query('from')),
            self::dateOrNull($request->query('to')),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function period(string $today, string $createdOn): array
    {
        $yesterday = Carbon::parse($today, IncomeCalendar::timezone())->subDay()->toDateString();
        $weekStart = Carbon::parse($today, IncomeCalendar::timezone())->subDays(6)->toDateString();

        [$from, $to] = match ($this->range) {
            'today' => [$today, $today],
            'yesterday' => [$yesterday, $yesterday],
            '7d' => [$weekStart, $today],
            'custom' => [$this->from ?? $createdOn, $this->to ?? $today],
            default => [$createdOn, $today],
        };

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    public function showsSources(): bool
    {
        return $this->focus === 'all' || $this->focus === 'business';
    }

    public function showsReferrals(): bool
    {
        return $this->focus === 'all' || $this->focus === 'referral';
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function pick(mixed $value, array $allowed, string $default): string
    {
        $value = is_string($value) ? $value : '';

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $value);

        return $parsed && $parsed->format('Y-m-d') === $value ? $value : null;
    }
}
