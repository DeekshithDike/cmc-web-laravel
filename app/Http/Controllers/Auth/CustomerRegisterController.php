<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TreePosition;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'formAction' => route('customer.register.save', absolute: false),
            'heading' => 'Join '.config('citymax.name'),
        ]);
    }

    public function store(Request $request, MembershipService $membership, PaymentService $payments): RedirectResponse|JsonResponse
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
            Log::warning('Customer register rejected: invite missing', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);

            return $this->failCheckout('Registration requires a valid invite link.', 'landing');
        }

        $package = Package::query()->whereKey((int) $request->input('package_id'))->where('is_active', true)->first();
        if (! $package) {
            Log::warning('Customer register rejected: package not found', [
                'package_id' => $request->input('package_id'),
                'email' => $request->input('email'),
            ]);

            return $this->failCheckout('Selected package is not available. Please choose another package.');
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
        } catch (Throwable $e) {
            return $this->checkoutFailed($e, [
                'flow' => 'invite_register',
                'order' => $orderId,
                'email' => $data['email'],
                'parent_id' => $data['parent_id'],
                'position' => $data['position'],
                'package_id' => $data['package_id'],
            ]);
        }

        return $this->redirectToCheckout($payments, $result, $orderId, [
            'flow' => 'invite_register',
            'email' => $data['email'],
        ], 'invite');
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
            'formAction' => route('customer.register.special.save', absolute: false),
            'heading' => 'Activate Power ID',
        ]);
    }

    public function specialStore(Request $request, PaymentService $payments): RedirectResponse|JsonResponse
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
            Log::warning('Power ID register rejected: invalid or already active', [
                'power_id' => $sessionPowerId,
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);

            return $this->failCheckout('This Power ID link is invalid or already activated.', 'landing');
        }

        $package = Package::query()->whereKey((int) $request->input('package_id'))->where('is_active', true)->first();
        if (! $package) {
            Log::warning('Power ID register rejected: package not found', [
                'package_id' => $request->input('package_id'),
                'email' => $request->input('email'),
            ]);

            return $this->failCheckout('Selected package is not available. Please choose another package.');
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
        } catch (Throwable $e) {
            return $this->checkoutFailed($e, [
                'flow' => 'power_register',
                'order' => $orderId,
                'email' => $request->input('email'),
                'power_id' => $power->id,
                'package_id' => (int) $package->id,
            ]);
        }

        return $this->redirectToCheckout($payments, $result, $orderId, [
            'flow' => 'power_register',
            'email' => $request->input('email'),
            'power_id' => $power->id,
        ], 'power_activation');
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

    /**
     * @param  array{transaction?: mixed, redirect_url?: string|null, message?: string}  $result
     * @param  array<string, mixed>  $context
     */
    private function redirectToCheckout(
        PaymentService $payments,
        array $result,
        string $orderId,
        array $context,
        ?string $forgetSessionKey = null,
    ): RedirectResponse|JsonResponse {
        $url = trim((string) ($result['redirect_url'] ?? ''));
        if ($url !== '' && $this->validCheckoutUrl($url)) {
            return $this->checkoutSuccess($url, $forgetSessionKey);
        }

        if ($payments->requiresLiveCheckout()) {
            return $this->checkoutFailed(new \RuntimeException('Payment checkout URL was missing or invalid after invoice create.'), [
                ...$context,
                'order' => $orderId,
            ]);
        }

        return $this->checkoutSuccess(
            route('customer.payment.success', ['ref' => $orderId]),
            $forgetSessionKey
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function checkoutFailed(Throwable $e, array $context): RedirectResponse|JsonResponse
    {
        $ref = 'REG-'.strtoupper(Str::random(6));

        Log::error('Customer checkout failed', [
            'ref' => $ref,
            'type' => $e::class,
            'error' => $e->getMessage(),
            'previous' => $e->getPrevious()?->getMessage(),
            'file' => $e->getFile().':'.$e->getLine(),
            ...$context,
        ]);

        return $this->failCheckout($this->customerCheckoutMessage($e, $ref));
    }

    private function checkoutSuccess(string $url, ?string $forgetSessionKey = null): RedirectResponse|JsonResponse
    {
        if ($forgetSessionKey !== null) {
            request()->session()->forget($forgetSessionKey);
        }

        if ($this->wantsJsonCheckout()) {
            return response()->json([
                'ok' => true,
                'redirect_url' => $url,
            ]);
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? redirect()->away($url)
            : redirect()->to($url);
    }

    private function validCheckoutUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if ($host === 'nowpayments.io' || str_ends_with($host, '.nowpayments.io')) {
            return $scheme === 'https';
        }

        $appHost = strtolower((string) parse_url(request()->getSchemeAndHttpHost(), PHP_URL_HOST));

        return $appHost !== '' && $host === $appHost;
    }

    private function failCheckout(string $message, ?string $route = null): RedirectResponse|JsonResponse
    {
        if ($this->wantsJsonCheckout()) {
            return response()->json([
                'ok' => false,
                'error' => $message,
            ], 422);
        }

        if ($route !== null) {
            return redirect()->route($route)->with('error', $message);
        }

        return back()->withInput()->with('error', $message);
    }

    private function wantsJsonCheckout(): bool
    {
        $request = request();

        return $request->expectsJson() || $request->ajax();
    }

    private function customerCheckoutMessage(Throwable $e, string $ref): string
    {
        if ($e instanceof InvalidArgumentException) {
            return $e->getMessage();
        }

        $root = $e->getPrevious() ?? $e;
        if ($root instanceof ConnectionException) {
            return 'Payment service is temporarily unreachable. Please try again in a few minutes. Support code: '.$ref.'.';
        }

        return 'We could not start payment. Please try again. If it continues, contact support with code '.$ref.'.';
    }
}
