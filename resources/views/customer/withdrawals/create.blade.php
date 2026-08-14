@extends('layouts.customer')
@section('title', 'Withdraw')
@section('heading', 'Withdrawal Now')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4 max-w-5xl">
    <section class="cmc-hero-wallet lg:col-span-2 p-6">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs uppercase tracking-wider text-white/70">Wallet balance</p>
            <span class="inline-flex w-10 h-10 items-center justify-center rounded-xl bg-white/15 text-xl"><i class="ph ph-wallet"></i></span>
        </div>
        <p class="text-4xl font-bold mt-2">${{ number_format((float) $user->wallet_balance, 2) }}</p>
        <div class="mt-5 space-y-2 text-sm text-white/80">
            <p class="inline-flex items-center gap-2"><i class="ph ph-shield-check"></i> Secure USDT payout</p>
            <p class="inline-flex items-center gap-2"><i class="ph ph-clock"></i> Processed after review</p>
            <p class="inline-flex items-center gap-2"><i class="ph ph-currency-circle-dollar"></i> USDT TRC-20 or BEP-20</p>
        </div>
        <p class="mt-4 text-xs text-white/70 leading-relaxed">Paste your own USDT address. We detect the network from the address — you do not select TRC-20 or BEP-20.</p>
    </section>

    <section class="cmc-panel lg:col-span-3 p-6">
        <div class="flex items-center gap-3 mb-5">
            <span class="cmc-stat-icon"><i class="ph ph-hand-withdraw"></i></span>
            <div>
                <h2 class="text-base font-semibold text-heading m-0">New withdrawal</h2>
                <p class="text-xs text-muted m-0">Minimum ${{ number_format((float) $minimum, 2) }} · Fee ${{ number_format((float) $fee, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
            <div class="rounded-xl border border-primary/20 bg-primary/5 p-3">
                <p class="text-xs font-semibold text-heading mb-1"><i class="ph ph-check-circle text-primary"></i> TRC-20 (Tron)</p>
                <p class="text-[11px] text-muted m-0 leading-relaxed">Starts with <code class="text-heading">T</code> and is 34 characters. Example: <code class="break-all text-heading">TEmGw…hfS</code></p>
            </div>
            <div class="rounded-xl border border-primary/20 bg-primary/5 p-3">
                <p class="text-xs font-semibold text-heading mb-1"><i class="ph ph-check-circle text-primary"></i> BEP-20 (BNB Smart Chain)</p>
                <p class="text-[11px] text-muted m-0 leading-relaxed">Starts with <code class="text-heading">0x</code> and is 42 characters. Use a BSC USDT address, not Ethereum.</p>
            </div>
        </div>
        <p class="text-[11px] text-muted mb-4 leading-relaxed"><i class="ph ph-warning text-danger"></i> We do not pay Ethereum ERC-20. An <code>0x</code> address is sent as BEP-20. A wrong network can lose funds.</p>

        <form method="POST" action="{{ route('customer.withdrawals.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-text-secondary mb-1.5">Amount (USD)</label>
                <div class="relative">
                    <i class="ph ph-currency-dollar absolute left-3 top-1/2 -translate-y-1/2 text-muted"></i>
                    <input type="number" step="0.01" min="{{ $minimum }}" name="amount" value="{{ old('amount') }}" required class="w-full h-11 pl-9 pr-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
                </div>
                <p class="text-[11px] text-muted mt-1.5">You receive this amount minus the ${{ number_format((float) $fee, 2) }} fee.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-text-secondary mb-1.5">Your USDT wallet address</label>
                <div class="relative">
                    <i class="ph ph-wallet absolute left-3 top-1/2 -translate-y-1/2 text-muted"></i>
                    <input id="wd-address" type="text" name="wallet_address" value="{{ old('wallet_address') }}" required spellcheck="false" autocomplete="off" placeholder="Paste TRC-20 (T…) or BEP-20 (0x…) address" class="w-full h-11 pl-9 pr-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary font-mono">
                </div>
                <p id="wd-network" class="text-[11px] text-muted mt-1.5" role="status">Paste an address to see TRC-20 or BEP-20.</p>
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-11 px-5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
                Withdraw Now <i class="ph ph-arrow-right"></i>
            </button>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('wd-address');
    const hint = document.getElementById('wd-network');
    if (!input || !hint) return;

    const trc20 = /^T[1-9A-HJ-NP-Za-km-z]{33}$/;
    const bep20 = /^0x[a-fA-F0-9]{40}$/;

    const paint = () => {
        const value = input.value.trim();
        hint.classList.remove('text-success', 'text-danger', 'text-muted');
        if (!value) {
            hint.classList.add('text-muted');
            hint.textContent = 'Paste an address to see TRC-20 or BEP-20.';
            return;
        }
        if (trc20.test(value)) {
            hint.classList.add('text-success');
            hint.textContent = 'Detected: USDT TRC-20 (Tron).';
            return;
        }
        if (bep20.test(value)) {
            hint.classList.add('text-success');
            hint.textContent = 'Detected: USDT BEP-20 (BNB Smart Chain). Not Ethereum ERC-20.';
            return;
        }
        hint.classList.add('text-danger');
        hint.textContent = 'Not a valid TRC-20 or BEP-20 USDT address.';
    };

    input.addEventListener('input', paint);
    input.addEventListener('paste', () => setTimeout(paint, 0));
    paint();
})();
</script>
@endpush
