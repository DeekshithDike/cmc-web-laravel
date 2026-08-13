@extends('layouts.customer-guest')

@section('title', 'Register')

@section('content')
<h2 class="text-xl font-bold text-heading text-center">Join {{ config('citymax.name') }}</h2>
<p class="text-sm text-muted text-center mt-1">Complete your invite registration</p>

@include('partials.alerts')

<div class="mt-4 rounded-xl bg-subtle border border-border p-3 text-xs text-text-secondary">
    Placement <strong>#{{ $placementId }}</strong> · {{ ucfirst($position) }} · Sponsor <strong>#{{ $sponsorId }}</strong>
</div>

<form class="space-y-3 mt-4" method="POST" action="{{ route('customer.register.save') }}">
    @csrf
    <input type="hidden" name="parent_id" value="{{ $placementId }}">
    <input type="hidden" name="position" value="{{ $position }}">
    <input type="hidden" name="sponsor_id" value="{{ $sponsorId }}">
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Full Name</label>
        <input name="name" value="{{ old('name') }}" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Phone</label>
        <input name="phone" value="{{ old('phone') }}" class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Country</label>
        <input name="country" value="{{ old('country') }}" class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Package</label>
        <select name="package_id" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
            @foreach($packages as $package)
                <option value="{{ $package->id }}">{{ $package->name }} (${{ $package->amount }})</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors mt-2">
        Create account <i class="ph ph-arrow-right text-base"></i>
    </button>
</form>
@endsection
