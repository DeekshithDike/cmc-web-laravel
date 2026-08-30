<?php

namespace App\Console\Commands;

use App\Services\Income\RepairMissed16AugIncomeService;
use Illuminate\Console\Command;
use Throwable;

class RepairMissed16AugIncomeCommand extends Command
{
    protected $signature = 'income:repair-16-aug {--apply : Write the repair. Default is a dry run.}';

    protected $description = 'Backfill the 16 Aug 2026 daily run that never fired. Only members who existed that day. Later IDs are not paid again.';

    public function handle(RepairMissed16AugIncomeService $repair): int
    {
        try {
            $state = $repair->inspect();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($state['lines'] as $line) {
            $this->line($line);
        }

        if ($state['status'] === 'applied') {
            return self::SUCCESS;
        }

        $creditTotal = '0.00';
        foreach ($state['credits'] as $credit) {
            $creditTotal = bcadd($creditTotal, $credit['amount'], 2);
        }
        $this->newLine();
        $this->info(count($state['credits']).' unpaid wallet credits totaling $'.$creditTotal.'.');
        $this->comment('Uses live volume, later daily runs, and current wallets — not a frozen backup snapshot.');

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('Dry run only. No wallets or ledger rows were changed.');
            $this->warn('On production run: php artisan income:repair-16-aug --apply');

            return self::SUCCESS;
        }

        try {
            $result = $repair->apply();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Applied.');
        foreach ($result['lines'] as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
