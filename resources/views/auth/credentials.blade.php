@extends('layouts.customer-guest')

@section('title', 'Your login details')

@section('content')
<div class="text-center mb-1">
    <span class="cmc-chip"><i class="ph ph-key"></i> One-time view</span>
</div>
<h2 class="text-xl font-bold text-heading text-center mt-3">Save these details now</h2>
<p class="text-sm text-muted text-center mt-1">This page is shown once. We do not email or store the password in your session.</p>

@if (! $payload)
    <div class="mt-6 rounded-xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm">
        These credentials have already been viewed or have expired. Use Customer Login if you already saved them, or contact support.
    </div>
    <p class="text-sm text-muted text-center mt-5">
        <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="{{ route('customer.login') }}">Customer Login</a>
    </p>
@else
    <div class="mt-6 space-y-3 rounded-xl bg-primary/5 border border-primary/20 p-4 text-sm">
        <div>
            <p class="text-xs text-muted">Login ID</p>
            <p class="font-semibold text-heading text-lg">{{ $payload['login_id'] }}</p>
        </div>
        <div>
            <p class="text-xs text-muted">Password / code</p>
            <p class="font-semibold text-heading break-all">{{ $payload['password'] }}</p>
        </div>
    </div>
    @if (! empty($payload['continue_url']))
        <a href="{{ $payload['continue_url'] }}" class="mt-6 w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors shadow-lg shadow-primary/25">
            Continue <i class="ph ph-arrow-right text-base"></i>
        </a>
    @endif
    <p class="text-sm text-muted text-center mt-5">
        <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="{{ route('customer.login') }}">Go to Customer Login</a>
    </p>
@endif
@endsection
