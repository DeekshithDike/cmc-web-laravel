<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\CsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function activeUsers(): StreamedResponse
    {
        $query = User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->with(['package:id,name'])
            ->orderBy('id')
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'sponsor_id',
                'parent_id',
                'position',
                'package_id',
                'wallet_balance',
                'expiry_date',
                'created_at',
            ]);

        return CsvExporter::download('active-users.csv', [
            'ID', 'Name', 'Email', 'Phone', 'Sponsor ID', 'Parent ID', 'Position', 'Package', 'Wallet', 'Expiry', 'Registered',
        ], $this->mapUsers($query));
    }

    public function completedWithdrawals(): StreamedResponse
    {
        $query = Withdrawal::query()
            ->with(['user:id,name'])
            ->where('status', WithdrawalStatus::Completed)
            ->orderBy('id')
            ->select([
                'id',
                'user_id',
                'amount',
                'fee',
                'payable_amount',
                'wallet_address',
                'remarks',
                'processed_at',
            ]);

        return CsvExporter::download('completed-withdrawals.csv', [
            'ID', 'User ID', 'Name', 'Amount', 'Fee', 'Payable', 'Wallet', 'Remarks', 'Processed At',
        ], $this->mapWithdrawals($query));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Generator<int, array<int, scalar|null>>
     */
    private function mapUsers($query): \Generator
    {
        foreach ($query->lazyById(500) as $u) {
            yield [
                $u->id,
                $u->name,
                $u->email,
                $u->phone,
                $u->sponsor_id,
                $u->parent_id,
                $u->position instanceof \BackedEnum ? $u->position->value : $u->position,
                $u->package->name ?? '',
                $u->wallet_balance,
                $u->expiry_date?->format('Y-m-d'),
                $u->created_at?->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Withdrawal>  $query
     * @return \Generator<int, array<int, scalar|null>>
     */
    private function mapWithdrawals($query): \Generator
    {
        foreach ($query->lazyById(500) as $w) {
            yield [
                $w->id,
                $w->user_id,
                $w->user->name ?? '',
                $w->amount,
                $w->fee,
                $w->payable_amount,
                $w->wallet_address,
                $w->remarks,
                $w->processed_at?->format('Y-m-d H:i'),
            ];
        }
    }
}
