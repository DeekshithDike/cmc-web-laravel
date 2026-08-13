@extends('layouts.customer-guest')

@section('title', 'Register')

@section('content')
<div class="text-center mb-1">
    <span class="cmc-chip"><i class="ph ph-rocket-launch"></i> {{ $powerId ? 'Power ID' : 'Invite join' }}</span>
</div>
<h2 class="text-xl font-bold text-heading text-center mt-3">{{ $heading ?? 'Join '.config('citymax.name') }}</h2>
<p class="text-sm text-muted text-center mt-1">{{ $powerId ? 'Pay to activate this reserved Power ID' : 'Complete your invite registration' }}</p>

@include('partials.alerts')

<div class="mt-4 rounded-xl bg-primary/5 border border-primary/20 p-3 text-xs text-text-secondary flex flex-wrap gap-x-3 gap-y-1 justify-center">
    <span><i class="ph ph-map-pin text-primary"></i> Placement <strong>#{{ $placementId }}</strong></span>
    <span><i class="ph ph-arrows-left-right text-primary"></i> {{ ucfirst($position) }}</span>
    <span><i class="ph ph-user text-primary"></i> Sponsor <strong>#{{ $sponsorId }}</strong></span>
</div>

<form class="space-y-3 mt-4" method="POST" action="{{ $formAction ?? route('customer.register.save') }}">
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
    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors mt-2 shadow-lg shadow-primary/25">
        Continue to payment <i class="ph ph-arrow-right text-base"></i>
    </button>
</form>
@endsection
