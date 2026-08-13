<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PaymentDetail;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeController extends Controller
{
    public function history(Request $request): View
    {
        $rows = PaymentDetail::query()
            ->where('user_id', $request->user()->id)
            ->latest('paid_on')
            ->paginate(25);

        return view('customer.income.history', compact('rows'));
    }
}
