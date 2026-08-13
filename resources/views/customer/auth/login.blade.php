@extends('layouts.customer-guest')

@section('title', 'Customer Login')

@section('content')
<h2 class="text-xl font-bold text-heading text-center">Welcome back</h2>
<p class="text-sm text-muted text-center mt-1">Sign in to your {{ config('citymax.name') }} account</p>

@include('partials.alerts')

<form class="space-y-4 mt-6" method="POST" action="{{ route('customer.login.submit') }}">
    @csrf
    <div>
        <label for="login_id" class="block text-xs font-medium text-text-secondary mb-1.5">Login ID</label>
        <div class="relative">
            <i class="ph ph-identification-badge absolute left-3 top-1/2 -translate-y-1/2 text-muted"></i>
            <input type="text" id="login_id" name="login_id" value="{{ old('login_id') }}" required autofocus autocomplete="username" placeholder="Your member ID"
                class="w-full h-11 pl-9 pr-3 rounded-xl bg-subtle border border-border text-sm text-text placeholder:text-faint focus:outline-none focus:border-primary transition-colors">
        </div>
    </div>
    <div>
        <label for="password" class="block text-xs font-medium text-text-secondary mb-1.5">Code</label>
        <div class="relative">
            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your code"
                class="w-full h-11 pl-3 pr-10 rounded-xl bg-subtle border border-border text-sm text-text placeholder:text-faint focus:outline-none focus:border-primary transition-colors">
            <button type="button" data-password-toggle aria-label="Show password" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:text-heading hover:bg-subtle transition-colors">
                <i class="ph ph-eye text-lg"></i>
            </button>
        </div>
    </div>
    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
        <input type="checkbox" name="remember" value="1" class="accent-primary">
        <span class="text-xs text-text-secondary">Keep me signed in</span>
    </label>
    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
        Sign in <i class="ph ph-arrow-right text-base"></i>
    </button>
</form>
<p class="text-sm text-muted text-center mt-5">
    <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="{{ route('landing') }}">Back to home</a>
</p>
@endsection
