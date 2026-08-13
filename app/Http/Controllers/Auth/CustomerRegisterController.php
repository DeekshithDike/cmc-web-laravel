<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class CustomerRegisterController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $placementId = (int) $request->query('placementID');
        $position = $request->query('position');
        $sponsorId = (int) ($request->query('sponsorID') ?: $placementId);

        if (! $placementId || ! in_array($position, ['left', 'right'], true)) {
            return redirect()->route('landing')->with('error', 'Registration requires a valid invite link.');
        }

        if (! User::query()->whereKey($placementId)->where('is_active', true)->exists()) {
            return redirect()->route('landing')->with('error', 'Invalid placement ID.');
        }

        if ($sponsorId !== $placementId
            && ! User::query()->whereKey($sponsorId)->where('is_active', true)->exists()) {
            $sponsorId = $placementId;
        }

        $request->session()->put('invite', [
            'parent_id' => $placementId,
            'position' => $position,
            'sponsor_id' => $sponsorId,
        ]);

        return view('customer.auth.register', [
            'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'placementId' => $placementId,
            'position' => $position,
            'sponsorId' => $sponsorId,
        ]);
    }

    public function store(Request $request, MembershipService $membership, PaymentService $payments): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:50'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $invite = $this->resolvedInvite($request);
        if ($invite === null) {
            return redirect()->route('landing')->with('error', 'Registration requires a valid invite link.');
        }

        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'country' => $request->input('country'),
            'package_id' => (int) $request->input('package_id'),
            'parent_id' => $invite['parent_id'],
            'position' => $invite['position'],
            'sponsor_id' => $invite['sponsor_id'],
        ];

        try {
            $awaitingPayment = $payments->requiresLiveCheckout();
            $user = $membership->createActiveMember($data, $awaitingPayment);
            $package = $user->package;
            $result = $payments->start($user, (float) ($package->amount ?? 0), null, [
                'package_id' => $package?->id,
                'source' => 'invite_register',
                'success_url' => route('customer.login'),
                'cancel_url' => route('customer.login'),
                'return_url' => route('customer.login'),
            ]);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $request->session()->forget('invite');

        $continueUrl = ! empty($result['redirect_url']) ? (string) $result['redirect_url'] : null;

        return $this->redirectToOneTimeCredentials((int) $user->id, (string) $user->plain_password, $continueUrl);
    }

    /**
     * @return array{parent_id:int,position:string,sponsor_id:int}|null
     */
    private function resolvedInvite(Request $request): ?array
    {
        $invite = $request->session()->get('invite');
        if (is_array($invite) && ! empty($invite['parent_id']) && in_array($invite['position'] ?? '', ['left', 'right'], true)) {
            return [
                'parent_id' => (int) $invite['parent_id'],
                'position' => (string) $invite['position'],
                'sponsor_id' => (int) ($invite['sponsor_id'] ?: $invite['parent_id']),
            ];
        }

        $parentId = (int) $request->input('parent_id');
        $position = (string) $request->input('position');
        if (! $parentId || ! in_array($position, ['left', 'right'], true)) {
            return null;
        }

        if (! User::query()->whereKey($parentId)->where('is_active', true)->exists()) {
            return null;
        }

        return [
            'parent_id' => $parentId,
            'position' => $position,
            'sponsor_id' => $parentId,
        ];
    }
}
