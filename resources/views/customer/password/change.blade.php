@extends('layouts.customer')
@section('title', 'Change Password')
@section('heading', 'Change Password')
@section('content')
<div class="cmc-panel max-w-lg p-6">
    <div class="flex items-center gap-3 mb-5">
        <span class="cmc-stat-icon"><i class="ph ph-key"></i></span>
        <div>
            <h2 class="text-base font-semibold text-heading m-0">Account security</h2>
            <p class="text-xs text-muted m-0">Update your login code for this Customer ID</p>
        </div>
    </div>
    <form method="POST" action="{{ route('customer.password.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Current password</label>
            <input id="current_password" type="password" name="current_password" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">New password</label>
            <input id="password" type="password" name="password" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-11 px-5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
            Update password <i class="ph ph-shield-check"></i>
        </button>
    </form>
</div>
@endsection
