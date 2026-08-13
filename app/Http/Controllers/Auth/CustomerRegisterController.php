<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TreePosition;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'powerId' => null,
            'formAction' => route('customer.register.save'),
            'heading' => 'Join '.config('citymax.name'),
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

        $package = Package::query()->whereKey((int) $request->input('package_id'))->where('is_active', true)->first();
        if (! $package) {
            return back()->withInput()->with('error', 'Package not found.');
        }

        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'country' => $request->input('country'),
            'package_id' => (int) $package->id,
            'parent_id' => $invite['parent_id'],
            'position' => $invite['position'],
            'sponsor_id' => $invite['sponsor_id'],
        ];

        $orderId = 'CMC-INV-'.Str::upper(Str::random(12));

        try {
            $membership->assertInvitePlacementAvailable($data['parent_id'], $data['position']);

            $result = $payments->start(null, (float) $package->amount, null, [
                'order_id' => $orderId,
                'package_id' => $package->id,
                'source' => 'invite_register',
                'signup' => $data,
                'description' => 'City Max Crypto package #'.$package->id,
                'success_url' => route('customer.payment.success', ['ref' => $orderId]),
                'cancel_url' => route('customer.payment.cancel'),
                'return_url' => route('customer.payment.success', ['ref' => $orderId]),
            ]);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $request->session()->forget('invite');

        if (! empty($result['redirect_url'])) {
            return redirect()->away((string) $result['redirect_url']);
        }

        return redirect()->route('customer.payment.success', ['ref' => $orderId]);
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

    public function specialShow(Request $request): View|RedirectResponse
    {
        $power = $this->resolvePowerTarget($request->query('target'));
        if (! $power) {
            return redirect()->route('landing')->with('error', 'Invalid Power ID link.');
        }

        $request->session()->put('power_activation', ['power_id' => $power->id]);

        return view('customer.auth.register', [
            'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'placementId' => $power->parent_id,
            'position' => $power->position instanceof TreePosition
                ? $power->position->value
                : (string) $power->position,
            'sponsorId' => $power->sponsor_id,
            'powerId' => $power->id,
            'formAction' => route('customer.register.special.save'),
            'heading' => 'Activate Power ID',
        ]);
    }

    public function specialStore(Request $request, PaymentService $payments): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:50'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $sessionPowerId = (int) ($request->session()->get('power_activation.power_id') ?? 0);
        $power = User::query()
            ->whereKey($sessionPowerId)
            ->where('is_power_id', true)
            ->where('is_active', false)
            ->first();
        if (! $power) {
            return redirect()->route('landing')->with('error', 'Invalid Power ID link.');
        }

        $package = Package::query()->whereKey((int) $request->input('package_id'))->where('is_active', true)->first();
        if (! $package) {
            return back()->withInput()->with('error', 'Package not found.');
        }

        $orderId = 'CMC-PWR-'.Str::upper(Str::random(12));

        try {
            $result = $payments->start(null, (float) $package->amount, null, [
                'order_id' => $orderId,
                'package_id' => $package->id,
                'source' => 'power_register',
                'power_activation' => [
                    'power_id' => $power->id,
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'phone' => $request->input('phone'),
                    'country' => $request->input('country'),
                    'package_id' => (int) $package->id,
                ],
                'description' => 'City Max Crypto Power ID #'.$power->id,
                'success_url' => route('customer.payment.success', ['ref' => $orderId]),
                'cancel_url' => route('customer.payment.cancel'),
                'return_url' => route('customer.payment.success', ['ref' => $orderId]),
            ]);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $request->session()->forget('power_activation');

        if (! empty($result['redirect_url'])) {
            return redirect()->away((string) $result['redirect_url']);
        }

        return redirect()->route('customer.payment.success', ['ref' => $orderId]);
    }

    private function resolvePowerTarget(mixed $target): ?User
    {
        if (! is_string($target) || $target === '') {
            return null;
        }

        try {
            $powerId = (int) decrypt($target);
        } catch (Throwable) {
            return null;
        }

        return User::query()
            ->whereKey($powerId)
            ->where('is_power_id', true)
            ->where('is_active', false)
            ->first();
    }
}
