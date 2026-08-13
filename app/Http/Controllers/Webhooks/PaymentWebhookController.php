<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use App\Services\Payouts\PayoutGatewayManager;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function handle(
        string $provider,
        Request $request,
        PaymentGatewayManager $gateways,
        PaymentService $payments,
    ): JsonResponse {
        try {
            $enum = PaymentProvider::from($provider);
        } catch (Throwable) {
            return response()->json(['ok' => false, 'error' => 'Unknown provider'], 404);
        }

        $gateway = $gateways->driver($enum);

        if (! $gateway->verifyWebhook($request)) {
            Log::warning('Payment IPN signature failed', ['provider' => $provider]);

            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        $transaction = $gateway->findWebhookTransaction($request);
        if (! $transaction) {
            Log::warning('Payment IPN for unknown transaction', [
                'provider' => $provider,
                'order_id' => $request->input('order_id'),
                'payment_id' => $request->input('payment_id'),
            ]);

            return response()->json(['ok' => false, 'error' => 'Transaction not found'], 404);
        }

        if (in_array($transaction->status, ['completed', 'failed'], true)) {
            return response()->json([
                'ok' => true,
                'status' => $transaction->status,
                'idempotent' => true,
            ]);
        }

        $status = $gateway->webhookStatus($request);
        if ($status === 'pending') {
            return response()->json(['ok' => true, 'status' => 'pending']);
        }

        $payments->confirm($transaction, [
            'status' => $status,
            'provider_ref' => $transaction->provider_ref,
            'webhook' => $request->all(),
        ]);

        return response()->json(['ok' => true, 'status' => $status]);
    }

    public function payout(
        string $provider,
        Request $request,
        PayoutGatewayManager $gateways,
        WithdrawalService $withdrawals,
    ): JsonResponse {
        try {
            $enum = PaymentProvider::from($provider);
        } catch (Throwable) {
            return response()->json(['ok' => false, 'error' => 'Unknown provider'], 404);
        }

        $gateway = $gateways->driver($enum);

        if (! $gateway->verifyWebhook($request)) {
            Log::warning('Payout IPN signature failed', ['provider' => $provider]);

            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        $withdrawal = $gateway->findWebhookWithdrawal($request);
        if (! $withdrawal) {
            Log::warning('Payout IPN for unknown withdrawal', [
                'provider' => $provider,
                'payout_id' => $request->input('id'),
            ]);

            return response()->json(['ok' => false, 'error' => 'Withdrawal not found'], 404);
        }

        $status = $gateway->webhookStatus($request);

        try {
            $withdrawals->applyPayoutWebhook($withdrawal, $status, [
                'provider_ref' => $request->input('id'),
                'webhook' => $request->all(),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'status' => $status]);
    }
}
