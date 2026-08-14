<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Services\Income\DailyIncomeService;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class IncomeController extends Controller
{
    public function daily(Request $request, DailyIncomeService $income): View
    {
        $asOf = $income->previousDate();
        $existing = DailyIncomeRun::query()->whereDate('as_of', $asOf)->first();
        $q = AdminList::search($request);
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $rows = PaymentDetail::query()
            ->with('user:id,name')
            ->when($from !== '', fn ($query) => $query->whereDate('paid_on', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('paid_on', '<=', $to))
            ->when($q !== '', function ($query) use ($q) {
                if (AdminList::isNumericId($q)) {
                    $query->where('user_id', (int) $q);

                    return;
                }

                $like = AdminList::like($q);
                $query->whereHas('user', function ($userQuery) use ($like) {
                    $userQuery->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->latest('paid_on')
            ->latest('id')
            ->paginate(AdminList::perPage($request))
            ->withQueryString();

        return view('admin.income.daily', compact('rows', 'asOf', 'existing', 'q', 'from', 'to'));
    }

    public function run(DailyIncomeService $income): RedirectResponse
    {
        try {
            $result = $income->run(null, 'admin');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['skipped']) {
            return back()->with('info', $result['message']);
        }

        return back()->with('success', $result['message']);
    }
}
