<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Verification\CustomerVerificationService;
use App\Support\AdminList;
use App\Support\VerificationFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function index(Request $request, CustomerVerificationService $verification): View
    {
        $q = mb_substr(AdminList::search($request), 0, 100);
        $filters = VerificationFilters::fromRequest($request);

        if ($q === '') {
            return view('admin.verification.index', [
                'q' => $q,
                'filters' => $filters,
                'report' => null,
                'matches' => null,
            ]);
        }

        $exact = AdminList::isNumericId($q)
            ? User::query()->where('role', UserRole::Customer)->whereKey((int) $q)->first()
            : null;

        if ($exact !== null) {
            return view('admin.verification.index', [
                'q' => $q,
                'filters' => $filters,
                'report' => $verification->report($exact, $filters, $request),
                'matches' => null,
            ]);
        }

        $matches = $verification->searchCustomers($q, AdminList::perPage($request));

        if ($matches->total() === 1) {
            /** @var User $customer */
            $customer = User::query()
                ->where('role', UserRole::Customer)
                ->tap(fn ($query) => AdminList::applySearch($query, $q, ['name', 'email', 'phone']))
                ->firstOrFail();

            return view('admin.verification.index', [
                'q' => $q,
                'filters' => $filters,
                'report' => $verification->report($customer, $filters, $request),
                'matches' => null,
            ]);
        }

        return view('admin.verification.index', [
            'q' => $q,
            'filters' => $filters,
            'report' => null,
            'matches' => $matches,
        ]);
    }
}
