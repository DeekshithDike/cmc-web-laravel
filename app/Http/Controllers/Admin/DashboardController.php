<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Business\BusinessVolumeService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BusinessVolumeService $volumes): View
    {
        $business = $volumes->platformVolume();

        return view('admin.dashboard', [
            'stats' => [
                'active_users' => User::query()
                    ->where('role', UserRole::Customer)
                    ->where('is_active', true)
                    ->where('payment_status', true)
                    ->count(),
                'today_users' => User::query()
                    ->where('role', UserRole::Customer)
                    ->whereDate('created_at', now()->toDateString())
                    ->count(),
                'power_ids' => User::query()->where('is_power_id', true)->where('is_active', false)->count(),
                'pending_withdrawals' => Withdrawal::query()
                    ->where('status', WithdrawalStatus::Pending)
                    ->count(),
                'total_business' => $business['total'],
                'today_business' => $business['today'],
                'total_withdrawal' => number_format(
                    (float) Withdrawal::query()->where('status', WithdrawalStatus::Completed)->sum('amount'),
                    2,
                    '.',
                    ''
                ),
                'today_withdrawal' => number_format(
                    (float) Withdrawal::query()
                        ->where('status', WithdrawalStatus::Completed)
                        ->whereDate('processed_at', now()->toDateString())
                        ->sum('amount'),
                    2,
                    '.',
                    ''
                ),
            ],
        ]);
    }
}
