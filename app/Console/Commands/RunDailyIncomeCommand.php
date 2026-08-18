<?php

namespace App\Console\Commands;

use App\Services\Income\DailyIncomeService;
use App\Support\IncomeCalendar;
use Illuminate\Console\Command;

class RunDailyIncomeCommand extends Command
{
    protected $signature = 'income:daily {--date= : Malaysia calendar day Y-m-d. Default: yesterday.}';

    protected $description = 'Pay previous Malaysia calendar day ROI, binary, and referral. Skips if that day was already calculated.';

    public function handle(DailyIncomeService $income): int
    {
        $date = trim((string) $this->option('date'));
        if ($date !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->error('Date must be Y-m-d.');

                return self::FAILURE;
            }

            if ($date > IncomeCalendar::today()) {
                $this->error('Cannot pay a future Malaysia calendar day.');

                return self::FAILURE;
            }
        }

        $result = $income->run($date !== '' ? $date : null, 'cron');

        if ($result['skipped']) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
