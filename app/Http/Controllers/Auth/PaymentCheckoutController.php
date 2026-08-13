<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentCheckoutController extends Controller
{
    public function success(Request $request): View
    {
        $transaction = $this->findByRef((string) $request->query('ref', ''));

        return view('customer.auth.payment-success', [
            'transaction' => $transaction,
        ]);
    }

    public function cancel(): View
    {
        return view('customer.auth.payment-cancel');
    }

    private function findByRef(string $ref): ?PaymentTransaction
    {
        if ($ref === '') {
            return null;
        }

        return PaymentTransaction::query()
            ->where(function ($query) use ($ref) {
                $query->where('provider_ref', $ref)
                    ->orWhere('meta->order_id', $ref);
            })
            ->first();
    }
}
