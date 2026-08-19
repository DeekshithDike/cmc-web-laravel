@extends('layouts.customer-guest')

@section('title', 'Register')

@section('content')
@php $openJoin = ! empty($openJoin); @endphp
<div class="text-center mb-1">
    <span class="cmc-chip"><i class="ph ph-rocket-launch"></i> {{ $powerId ? 'Power ID' : ($openJoin ? 'Open join' : 'Invite join') }}</span>
</div>
<h2 class="text-xl font-bold text-heading text-center mt-3">{{ $heading ?? 'Join '.config('citymax.name') }}</h2>
<p class="text-sm text-muted text-center mt-1">{{ $powerId ? 'Pay to activate this reserved Power ID' : ($openJoin ? 'Enter placement and continue to payment' : 'Complete your invite registration') }}</p>

@include('partials.alerts')
<div id="cmc-js-error" class="hidden alert alert-danger mb-4 rounded-2xl border-2 border-danger bg-danger/15 text-danger px-4 py-3 text-sm font-medium" role="alert"></div>

@if (! $openJoin)
<div class="mt-4 rounded-xl bg-primary/5 border border-primary/20 p-3 text-xs text-text-secondary flex flex-wrap gap-x-3 gap-y-1 justify-center">
    <span><i class="ph ph-map-pin text-primary"></i> Placement ID <strong>#{{ $placementId }}</strong></span>
    <span><i class="ph ph-arrows-left-right text-primary"></i> {{ ucfirst($position) }}</span>
    <span><i class="ph ph-user text-primary"></i> Sponsor ID <strong>#{{ $sponsorId }}</strong></span>
</div>
@endif

<form id="register-form" class="space-y-3 mt-4" method="POST" action="{{ $formAction }}">
    @csrf
    @if ($openJoin)
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Placement ID</label>
        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" name="parent_id" value="{{ old('parent_id', $placementId) }}" required class="w-full h-11 px-3 rounded-xl bg-subtle border {{ $errors->has('parent_id') ? 'border-danger' : 'border-border' }} text-sm text-text focus:outline-none focus:border-primary">
        @error('parent_id')
            <p class="text-xs text-danger mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Position</label>
        <select name="position" required class="w-full h-11 px-3 rounded-xl bg-subtle border {{ $errors->has('position') ? 'border-danger' : 'border-border' }} text-sm text-text focus:outline-none focus:border-primary">
            <option value="">Select position</option>
            <option value="left" @selected((string) old('position', $position) === 'left')>Left</option>
            <option value="right" @selected((string) old('position', $position) === 'right')>Right</option>
        </select>
        @error('position')
            <p class="text-xs text-danger mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Sponsor ID</label>
        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" name="sponsor_id" value="{{ old('sponsor_id', $sponsorId) }}" class="w-full h-11 px-3 rounded-xl bg-subtle border {{ $errors->has('sponsor_id') ? 'border-danger' : 'border-border' }} text-sm text-text focus:outline-none focus:border-primary">
        <p class="text-[11px] text-muted mt-1">Optional. Pre-filled from the shared link when included. You can change it.</p>
        @error('sponsor_id')
            <p class="text-xs text-danger mt-1">{{ $message }}</p>
        @enderror
    </div>
    @else
    <input type="hidden" name="parent_id" value="{{ $placementId }}">
    <input type="hidden" name="position" value="{{ $position }}">
    <input type="hidden" name="sponsor_id" value="{{ $sponsorId }}">
    @endif
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Full Name</label>
        <input name="name" value="{{ old('name') }}" required class="w-full h-11 px-3 rounded-xl bg-subtle border {{ $errors->has('name') ? 'border-danger' : 'border-border' }} text-sm text-text focus:outline-none focus:border-primary">
        @error('name')
            <p class="text-xs text-danger mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required class="w-full h-11 px-3 rounded-xl bg-subtle border {{ $errors->has('email') ? 'border-danger' : 'border-border' }} text-sm text-text focus:outline-none focus:border-primary">
        @error('email')
            <p class="text-xs text-danger mt-1">{{ $message }}</p>
        @enderror
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
                <option value="{{ $package->id }}" @selected((string) old('package_id') === (string) $package->id)>{{ $package->name }} (${{ $package->amount }})</option>
            @endforeach
        </select>
    </div>
    <button id="register-submit" type="submit" class="w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors mt-2 shadow-lg shadow-primary/25 disabled:opacity-70 disabled:pointer-events-none disabled:cursor-not-allowed">
        Continue to payment <i class="ph ph-arrow-right text-base"></i>
    </button>
</form>
@endsection

@push('scripts')
<script src="{{ asset_ver('customer-assets/js/register.js') }}"></script>
@endpush
