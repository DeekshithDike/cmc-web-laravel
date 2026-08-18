<?php

namespace App\Console\Commands;

use App\Services\Income\DailyIncomeService;
use Illuminate\Console\Command;

class BackfillMissingRoiCommand extends Command
{
    protected $signature = 'income:backfill-roi {date : Malaysia calendar day Y-m-d}';

    protected $description = 'Credit 1% ROI for a past day without re-paying binary or referral. Skips members who already received ROI that day.';

    public function handle(DailyIncomeService $income): int
    {
        $date = (string) $this->argument('date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Date must be Y-m-d.');

            return self::FAILURE;
        }

        $result = $income->creditMissingRoi($date, 'admin');
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
