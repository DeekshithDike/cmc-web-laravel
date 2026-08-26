<?php

namespace App\Console\Commands;

use App\Services\Income\DailyIncomeService;
use App\Support\IncomeCalendar;
use Illuminate\Console\Command;

class BackfillZeroLedgerCommand extends Command
{
    protected $signature = 'income:backfill-zero-ledger {--date= : Malaysia calendar day Y-m-d. Default: all completed run days.}';

    protected $description = 'Insert $0 income history rows for completed daily runs that skipped members with nothing to pay. Does not credit wallets.';

    public function handle(DailyIncomeService $income): int
    {
        $date = trim((string) $this->option('date'));
        if ($date !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->error('Date must be Y-m-d.');

                return self::FAILURE;
            }

            if ($date > IncomeCalendar::today()) {
                $this->error('Cannot backfill a future Malaysia calendar day.');

                return self::FAILURE;
            }
        }

        $result = $income->backfillZeroLedger($date !== '' ? $date : null);
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
