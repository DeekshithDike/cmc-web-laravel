<?php

use App\Enums\UserRole;
use App\Models\RenewalHistory;
use App\Models\User;
use App\Support\ActivatedAtEstimator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('expiry_date');
        });

        User::query()
            ->where('role', UserRole::Customer)
            ->whereNull('activated_at')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                $ids = $users->pluck('id');
                $firstPrevious = RenewalHistory::query()
                    ->whereIn('user_id', $ids)
                    ->whereNotNull('previous_expiry')
                    ->orderBy('id')
                    ->get(['user_id', 'previous_expiry'])
                    ->unique('user_id')
                    ->keyBy('user_id');

                foreach ($users as $user) {
                    if ($user->is_power_id) {
                        continue;
                    }

                    $estimated = ActivatedAtEstimator::estimate(
                        $user->created_at,
                        $user->expiry_date,
                        $firstPrevious->get($user->id)?->previous_expiry,
                    );

                    if ($estimated === null && $user->is_active) {
                        $estimated = $user->created_at;
                    }

                    if ($estimated === null) {
                        continue;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'activated_at' => $estimated,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activated_at');
        });
    }
};
