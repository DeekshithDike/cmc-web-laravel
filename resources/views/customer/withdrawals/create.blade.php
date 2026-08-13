@extends('layouts.customer')
@section('title', 'Withdraw')
@section('heading', 'Withdrawal Now')
@section('content')
<div class="bg-surface border border-border rounded-2xl p-6 shadow-sm max-w-xl">
    <p class="text-sm text-muted mb-1">Wallet balance</p>
    <p class="text-3xl font-bold text-heading mb-4">${{ number_format((float)$user->wallet_balance, 2) }}</p>
    <p class="text-xs text-muted mb-4">Minimum ${{ number_format((float)$minimum, 2) }} · Fee ${{ number_format((float)$fee, 2) }} · USDT ERC-20 / BEP-20 address (<code>0x</code> + 40 hex characters)</p>
    <form method="POST" action="{{ route('customer.withdrawals.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Amount (USD)</label>
            <input type="number" step="0.01" name="amount" value="{{ old('amount') }}" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Wallet Address</label>
            <input type="text" name="wallet_address" value="{{ old('wallet_address') }}" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-11 px-5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
            Withdraw Now <i class="ph ph-arrow-right"></i>
        </button>
    </form>
</div>
@endsection
