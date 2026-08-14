<?php

namespace App\Services\Renewals;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\RenewalHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RenewalService
{
    public function renew(User $user, User $admin, ?string $notes = null): RenewalHistory
    {
        if ($user->role !== UserRole::Customer) {
            throw new InvalidArgumentException('Only customers can be renewed.');
        }

        $warningDays = (int) config('citymax.membership.expiry_warning_days');
        $weekdays = (int) config('citymax.membership.weekdays');

        if ($user->expiry_date && $user->expiry_date->gt(now()->addDays($warningDays))) {
            throw new InvalidArgumentException("Renewal allowed only within {$warningDays} days of expiry.");
        }

        return DB::transaction(function () use ($user, $admin, $notes, $weekdays) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->expiry_date?->toDateString();

            $start = $locked->expiry_date && $locked->expiry_date->gte(now()->startOfDay())
                ? $locked->expiry_date->copy()->addDay()
                : now();

            $newExpiry = $start->copy();
            $added = 0;
            while ($added < $weekdays) {
                $newExpiry->addDay();
                if (! $newExpiry->isWeekend()) {
                    $added++;
                }
            }

            $locked->expiry_date = $newExpiry->toDateString();
            $locked->status = UserStatus::Active;
            $locked->is_active = true;
            $locked->payment_status = true;
            $locked->save();

            return RenewalHistory::query()->create([
                'user_id' => $locked->id,
                'renewed_by' => $admin->id,
                'previous_expiry' => $previous,
                'new_expiry' => $newExpiry->toDateString(),
                'amount' => $locked->package?->amount ?? 0,
                'notes' => $notes ?? 'ADMIN',
            ]);
        });
    }
}
