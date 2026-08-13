<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentDetail;
use App\Services\Income\DailyIncomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class IncomeController extends Controller
{
    public function daily(): View
    {
        $rows = PaymentDetail::query()
            ->with('user:id,name')
            ->latest('paid_on')
            ->paginate(25);

        return view('admin.income.daily', compact('rows'));
    }

    public function run(Request $request, DailyIncomeService $income): RedirectResponse
    {
        $data = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $result = $income->run($data['as_of'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            "Daily income for {$result['asOf']}: {$result['processed']} members, \${$result['total']} paid."
        );
    }
}
