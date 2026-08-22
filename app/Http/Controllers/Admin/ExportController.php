<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\AdminList;
use App\Support\CsvExporter;
use App\Support\IncomeCalendar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function activeUsers(Request $request): StreamedResponse
    {
        $q = AdminList::search($request);
        $packageId = (int) $request->query('package_id', 0);

        $query = User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->when($packageId > 0, fn ($builder) => $builder->where('package_id', $packageId))
            ->tap(fn ($builder) => AdminList::applySearch($builder, $q, ['name', 'email', 'phone']))
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

    public function completedWithdrawals(Request $request): StreamedResponse
    {
        $q = AdminList::search($request);

        $query = Withdrawal::query()
            ->with(['user:id,name'])
            ->where('status', WithdrawalStatus::Completed)
            ->when($q !== '', function ($builder) use ($q) {
                if (AdminList::isNumericId($q)) {
                    $id = (int) $q;
                    $builder->where(function ($inner) use ($id) {
                        $inner->where('id', $id)->orWhere('user_id', $id);
                    });

                    return;
                }

                $like = AdminList::like($q);
                $builder->where(function ($inner) use ($like) {
                    $inner->where('wallet_address', 'like', $like)
                        ->orWhereHas('user', function ($userQuery) use ($like) {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
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
            'ID', 'Customer ID', 'Name', 'Amount', 'Fee', 'Payable', 'Wallet', 'Remarks', 'Processed At',
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
                $u->expiry_date ? IncomeCalendar::formatDate($u->expiry_date) : '',
                $u->created_at ? IncomeCalendar::formatDateTime($u->created_at) : '',
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
                $w->processed_at ? IncomeCalendar::formatDateTime($w->processed_at) : '',
            ];
        }
    }
}
