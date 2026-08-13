<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    public function credit(User $user, string|float $amount, ?string $reason = null): User
    {
        $amount = $this->normalizeAmount($amount);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than zero.');
        }

        return $this->mutate($user, 'credit', $amount, $reason);
    }

    public function debit(User $user, string|float $amount, ?string $reason = null): User
    {
        $amount = $this->normalizeAmount($amount);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be greater than zero.');
        }

        return $this->mutate($user, 'debit', $amount, $reason);
    }

    public function balance(User $user): string
    {
        return number_format((float) $user->fresh()->wallet_balance, 2, '.', '');
    }

    private function mutate(User $user, string $type, float $amount, ?string $reason): User
    {
        $run = function () use ($user, $type, $amount, $reason): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $formatted = number_format($amount, 2, '.', '');

            if ($type === 'debit') {
                if (bccomp((string) $locked->wallet_balance, $formatted, 2) < 0) {
                    throw new InvalidArgumentException('Insufficient wallet balance.');
                }
                $locked->wallet_balance = bcsub((string) $locked->wallet_balance, $formatted, 2);
            } else {
                $locked->wallet_balance = bcadd((string) $locked->wallet_balance, $formatted, 2);
            }

            $locked->save();

            WalletTransaction::query()->create([
                'user_id' => $locked->id,
                'type' => $type,
                'amount' => $formatted,
                'balance_after' => $locked->wallet_balance,
                'reason' => $reason,
            ]);

            return $locked;
        };

        return DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    private function normalizeAmount(string|float $amount): float
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        return round((float) $amount, 2);
    }
}
