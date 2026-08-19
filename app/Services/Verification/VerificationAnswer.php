<?php

namespace App\Services\Verification;

final class VerificationAnswer
{
    /**
     * @param  array{
     *     left: float,
     *     right: float,
     *     matched: float,
     *     roi: float,
     *     binary: float,
     *     referral: float,
     *     match_days: int
     * }  $totals
     * @return array{tone: string, text: string}
     */
    public static function make(
        string $focus,
        string $range,
        bool $eligible,
        bool $includesToday,
        bool $yesterdayCompleted,
        array $totals,
        float $binaryPercent,
        float $referralPercent,
        float $packageAmount,
        float $carryLeft,
        float $carryRight,
    ): array {
        if (! $eligible) {
            return [
                'tone' => 'warning',
                'text' => 'This ID is not eligible for daily income (inactive, unpaid, or expired). Business and stored referral rows can still be checked below.',
            ];
        }

        if ($range === 'today') {
            return [
                'tone' => 'warning',
                'text' => 'Today has not been paid yet — that is normal. At midnight Malaysia time the system pays yesterday, not today. Left and Right below are only today’s stored business. Matching, ROI, binary, and referral for today wait until tonight’s job. If yesterday still needs paying, use Daily Paid Income. Do not run income from this page.',
            ];
        }

        if (! $yesterdayCompleted && in_array($range, ['yesterday', 'all', '7d'], true)) {
            return [
                'tone' => 'warning',
                'text' => 'Yesterday’s daily income has not been marked completed. If members say nothing was paid, check Daily Paid Income and run yesterday once there — not from this page.',
            ];
        }

        $money = static fn (float $n): string => '$'.number_format($n, 2);

        if ($focus === 'roi') {
            return [
                'tone' => $totals['roi'] > 0 ? 'success' : 'info',
                'text' => $totals['roi'] > 0
                    ? 'ROI was paid in this range ('.$money($totals['roi']).'). Saturday and Sunday are $0 by rule. Today stays pending until midnight Malaysia time.'
                    : 'No ROI in this range. Weekends are skipped. If this is a weekday and the ID was active, yesterday’s run may still be pending or the package ROI percent is 0.',
            ];
        }

        if ($focus === 'binary') {
            $oneSided = ($totals['left'] > 0 && $totals['right'] <= 0)
                || ($totals['right'] > 0 && $totals['left'] <= 0);

            $text = 'Binary paid '.$money($totals['binary']).' from '.$money($totals['matched']).' matched business over '.$totals['match_days'].' day(s) ('
                .rtrim(rtrim(number_format($binaryPercent, 2), '0'), '.').'%, cap '.$money($packageAmount).').';

            if ($oneSided && $totals['matched'] <= 0) {
                $text .= ' No match because one side is $0 — left/right business is not the same as a match.';
            }

            $text .= ' Leftover carry: Left '.$money($carryLeft).' / Right '.$money($carryRight).'.';

            return ['tone' => 'info', 'text' => $text];
        }

        if ($focus === 'business') {
            return [
                'tone' => 'info',
                'text' => 'Left '.$money($totals['left']).' · Right '.$money($totals['right']).' in this range. Amounts are downline package dollars on this ID’s tree legs, not headcount, and not the sponsor list. Use “Left / Right breakdown” to see each downline ID.',
            ];
        }

        if ($focus === 'referral') {
            return [
                'tone' => $totals['referral'] > 0 ? 'success' : 'info',
                'text' => $totals['referral'] > 0
                    ? 'Referral paid '.$money($totals['referral']).' ('.rtrim(rtrim(number_format($referralPercent, 2), '0'), '.').'% of stored package volume after the daily run). The full package is stored for the sponsor; it is not credited 100% on join. Referral follows sponsor, not placement.'
                    : 'No referral paid in this range. Check whether the downline is activated, whether this ID is the sponsor, and whether the daily run after join has completed.',
            ];
        }

        $pendingNote = $includesToday ? ' Today’s row is pending until 00:00 Malaysia.' : '';

        return [
            'tone' => 'info',
            'text' => 'Eligible. ROI '.$money($totals['roi']).' · Binary '.$money($totals['binary']).' · Referral '.$money($totals['referral']).'. Left '.$money($totals['left']).' / Right '.$money($totals['right']).' · matched business '.$money($totals['matched']).' on '.$totals['match_days'].' day(s).'.$pendingNote,
        ];
    }
}
