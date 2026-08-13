<?php

namespace App\Console\Commands;

use App\Services\Income\DailyIncomeService;
use Illuminate\Console\Command;

class RunDailyIncomeCommand extends Command
{
    protected $signature = 'income:daily';

    protected $description = 'Pay yesterday ROI, binary, and referral. Skips if that day was already calculated.';

    public function handle(DailyIncomeService $income): int
    {
        $result = $income->run(null, 'cron');

        if ($result['skipped']) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
