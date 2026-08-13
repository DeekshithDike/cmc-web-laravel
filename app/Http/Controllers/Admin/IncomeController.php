<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Services\Income\DailyIncomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class IncomeController extends Controller
{
    public function daily(DailyIncomeService $income): View
    {
        $asOf = $income->previousDate();
        $existing = DailyIncomeRun::query()->whereDate('as_of', $asOf)->first();
        $rows = PaymentDetail::query()
            ->with('user:id,name')
            ->latest('paid_on')
            ->paginate(25);

        return view('admin.income.daily', compact('rows', 'asOf', 'existing'));
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
