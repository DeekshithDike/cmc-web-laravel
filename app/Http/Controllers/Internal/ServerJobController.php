<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\Income\DailyIncomeService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServerJobController extends Controller
{
    public function income(Request $request, DailyIncomeService $income): JsonResponse
    {
        if (! $request->isMethod('POST')) {
            abort(404);
        }

        set_time_limit(0);

        try {
            $result = $income->run(null, 'cron');
        } catch (Throwable $e) {
            Log::error('Server job income failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Income run failed.'], 500);
        }

        return response()->json(['ok' => true] + $result);
    }

    public function payments(Request $request, PaymentService $payments): JsonResponse
    {
        if (! $request->isMethod('POST')) {
            abort(404);
        }

        set_time_limit(180);

        try {
            $result = $payments->syncPendingPayments();
        } catch (Throwable $e) {
            Log::error('Server job payment sync failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Payment sync failed.'], 500);
        }

        return response()->json(['ok' => true] + $result);
    }
}
