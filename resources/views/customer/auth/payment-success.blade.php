@extends('layouts.customer-guest')

@section('title', 'Registration successful')

@section('content')
<h2 class="text-xl font-bold text-heading text-center">Registration successful</h2>
<p class="text-sm text-muted text-center mt-2">
    Your account will be activated within an hour. Login ID and Password will be sent to your registered email address after activation.
</p>

@if ($transaction && $transaction->status === 'completed' && ! empty($transaction->meta['credentials_token']))
    <a href="{{ route('credentials.show', ['token' => $transaction->meta['credentials_token']]) }}" class="mt-6 w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
        View login details <i class="ph ph-arrow-right text-base"></i>
    </a>
@elseif ($transaction && $transaction->status === 'failed')
    <div class="mt-6 rounded-xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm">
        Payment was not completed. Signup again using the invite link.
    </div>
@endif

<p class="text-sm text-muted text-center mt-5">
    <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="{{ route('customer.login') }}">Customer Login</a>
</p>
@endsection
