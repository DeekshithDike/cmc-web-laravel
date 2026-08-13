<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function index(): View
    {
        $transactions = PaymentTransaction::query()->with(['user', 'package'])->latest()->paginate(25);

        return view('admin.payments.index', compact('transactions'));
    }

    public function start(Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);

        try {
            $result = $payments->start($user, (float) $data['amount'], null, [
                'package_id' => $data['package_id'] ?? $user->package_id,
                'source' => 'admin',
            ]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $result['message'] ?? 'Payment started: '.$result['transaction']->provider_ref;
        if (! empty($result['redirect_url'])) {
            $message .= ' Checkout: '.$result['redirect_url'];
        }

        return back()->with('success', $message);
    }

    public function confirm(Request $request, int $payment, PaymentService $payments): RedirectResponse
    {
        $model = PaymentTransaction::query()->findOrFail($payment);

        try {
            $payments->confirm($model, ['confirmed_by' => $request->user()->id]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment confirmed.');
    }
}
