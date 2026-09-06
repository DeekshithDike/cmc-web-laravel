<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $q = AdminList::search($request);
        $status = trim((string) $request->query('status', ''));

        $transactions = PaymentTransaction::query()
            ->with(['user:id,name,email', 'package:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                if (AdminList::isNumericId($q)) {
                    $id = (int) $q;
                    $query->where(function ($inner) use ($id) {
                        $inner->where('id', $id)->orWhere('user_id', $id);
                    });

                    return;
                }

                $like = AdminList::like($q);
                $query->where(function ($inner) use ($like) {
                    $inner->where('provider_ref', 'like', $like)
                        ->orWhere('provider', 'like', $like)
                        ->orWhereHas('user', function ($userQuery) use ($like) {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->latest('id')
            ->paginate(AdminList::perPage($request))
            ->withQueryString();

        $showPaymentSync = PaymentTransaction::query()
            ->where('status', 'pending')
            ->where('provider', PaymentProvider::NowPayments)
            ->exists();

        return view('admin.payments.index', compact('transactions', 'q', 'status', 'showPaymentSync'));
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
            $payments->confirm($model, ['confirmed_by' => $request->user('admin')->id]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment confirmed.');
    }

    public function syncPending(PaymentService $payments): RedirectResponse
    {
        try {
            $result = $payments->syncPendingPayments();
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $parts = [];
        if ($result['completed'] > 0) {
            $parts[] = $result['completed'].' marked paid and activated';
        }
        if ($result['failed'] > 0) {
            $parts[] = $result['failed'].' marked failed';
        }
        if ($result['unchanged'] > 0) {
            $parts[] = $result['unchanged'].' still pending';
        }
        if ($result['skipped'] > 0) {
            $parts[] = $result['skipped'].' skipped';
        }

        $message = $result['checked'] === 0
            ? 'No pending NOWPayments payments to sync.'
            : 'Checked '.$result['checked'].' pending payment(s)'
                .($parts !== [] ? ': '.implode(', ', $parts) : '')
                .'.';

        $redirect = back()->with('success', $message);
        if ($result['errors'] !== []) {
            $redirect->with('error', implode(' ', $result['errors']));
        }

        return $redirect;
    }
}
