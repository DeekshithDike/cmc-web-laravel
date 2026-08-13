<?php

namespace App\Console\Commands;

use App\Services\Income\DailyIncomeService;
use Illuminate\Console\Command;

class RunDailyIncomeCommand extends Command
{
    protected $signature = 'income:daily {--as-of= : Pay date YYYY-MM-DD (defaults to today)}';

    protected $description = 'Pay daily ROI and binary matching (Laravel owns money; Node calc stays notify-only).';

    public function handle(DailyIncomeService $income): int
    {
        $result = $income->run($this->option('as-of') ?: null);

        $this->info("Daily income {$result['asOf']}: {$result['processed']} members, \${$result['total']} paid.");

        return self::SUCCESS;
    }
}
