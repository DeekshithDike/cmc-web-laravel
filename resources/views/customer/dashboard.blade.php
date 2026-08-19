@extends('layouts.customer')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
@php
    $malaysiaNow = now('Asia/Kuala_Lumpur');
@endphp
<div class="mb-5 rounded-2xl border border-primary/40 bg-primary/10 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3 min-w-0">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary text-white text-xl flex-shrink-0">
            <i class="ph ph-clock"></i>
        </span>
        <div class="min-w-0">
            <p class="text-[11px] uppercase tracking-wider font-semibold text-primary">Server time · Malaysia</p>
            <p id="cmc-server-clock" class="text-lg sm:text-xl font-bold text-heading tabular-nums" data-server-ms="{{ $malaysiaNow->getTimestampMs() }}">
                {{ $malaysiaNow->format('l, d F Y · h:i:s A') }}
            </p>
        </div>
    </div>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary text-white px-3 py-1 text-xs font-semibold">
        <i class="ph ph-map-pin"></i> Malaysia (GMT+8)
    </span>
</div>

@if ($showExpiryWarning)
    <div class="mb-5 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm flex items-start gap-3">
        <i class="ph ph-warning-circle text-xl mt-0.5"></i>
        <div>
            <p class="font-semibold">Membership renewing soon</p>
            <p class="opacity-90">Expires in {{ $daysLeft }} day(s) on {{ $user->expiry_date?->format('Y-m-d') }}.</p>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <section class="cmc-hero-wallet xl:col-span-2 p-5 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wider text-white/70 mb-1">Available balance</p>
                <p class="text-4xl sm:text-5xl font-bold tracking-tight">${{ number_format((float) $user->wallet_balance, 2) }}</p>
                <p class="mt-2 text-sm text-white/75">Customer ID {{ $user->id }} · {{ $user->name }}</p>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-white/15 text-2xl">
                    <i class="ph ph-wallet"></i>
                </span>
                <p class="mt-2">
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide">
                        <i class="ph ph-currency-circle-dollar"></i> USDT
                    </span>
                </p>
                <p class="text-xs text-white/70 mt-1.5">TRC-20 / BEP-20</p>
            </div>
        </div>
        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('customer.withdrawals.create') }}" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-white text-primary font-semibold text-sm hover:bg-white/90 transition-colors">
                <i class="ph ph-hand-withdraw"></i> Withdraw
            </a>
            <a href="{{ route('customer.income.history') }}" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-white/10 text-white border border-white/25 text-sm font-medium hover:bg-white/15 transition-colors">
                <i class="ph ph-chart-line-up"></i> Income
            </a>
            <a href="{{ route('customer.tree') }}" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-white/10 text-white border border-white/25 text-sm font-medium hover:bg-white/15 transition-colors">
                <i class="ph ph-tree-structure"></i> My Tree
            </a>
        </div>
    </section>

    <section class="cmc-panel p-5 flex flex-col justify-between">
        <div>
            <span class="cmc-chip mb-3"><i class="ph ph-package"></i> Active package</span>
            <p class="text-3xl font-bold text-heading">${{ number_format((float) ($user->package->amount ?? 0), 2) }}</p>
            <p class="text-sm text-muted mt-1">{{ $user->package->name ?? 'No package' }}</p>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-primary/5 border border-primary/15 p-3">
                <p class="text-[11px] text-muted uppercase tracking-wide">Expiry</p>
                <p class="text-sm font-semibold text-heading mt-1">{{ $user->expiry_date?->format('Y-m-d') ?? '—' }}</p>
            </div>
            <div class="rounded-xl bg-primary/5 border border-primary/15 p-3">
                <p class="text-[11px] text-muted uppercase tracking-wide">Status</p>
                <p class="text-sm font-semibold text-success mt-1 inline-flex items-center gap-1"><i class="ph ph-check-circle"></i> Active</p>
            </div>
        </div>
    </section>
</div>

<section class="cmc-roi-wallet p-5 sm:p-6 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <span class="cmc-chip"><i class="ph ph-chart-line-up"></i> ROI wallet income</span>
            <p class="text-sm text-muted mt-2">Daily package ROI credited to your wallet</p>
        </div>
        <p class="text-xs font-semibold text-primary bg-primary/10 border border-primary/20 rounded-full px-3 py-1">
            {{ $roiDays }} {{ $roiDays === 1 ? 'day' : 'days' }} paid
        </p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="cmc-roi-metric p-4">
            <p class="text-[11px] text-muted uppercase tracking-wide">Last paid ROI</p>
            <p class="text-2xl sm:text-3xl font-bold text-heading tracking-tight mt-1">${{ number_format((float) $roiLastPaid, 2) }}</p>
            <p class="text-xs text-muted mt-1">{{ $roiLastPaidOn ? 'Credited on '.$roiLastPaidOn : 'No ROI credited yet' }}</p>
        </div>
        <div class="cmc-roi-metric p-4">
            <p class="text-[11px] text-muted uppercase tracking-wide">Total ROI</p>
            <p class="text-2xl sm:text-3xl font-bold text-heading tracking-tight mt-1">${{ number_format((float) $roiTotal, 2) }}</p>
            <p class="text-xs text-muted mt-1">All ROI credited so far</p>
        </div>
        <div class="cmc-roi-metric p-4">
            <p class="text-[11px] text-muted uppercase tracking-wide">Paid days</p>
            <p class="text-2xl sm:text-3xl font-bold text-heading tracking-tight mt-1">{{ $roiDays }}</p>
            <p class="text-xs text-muted mt-1">Weekdays ROI was paid</p>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach ([
        ['Today Left', '$'.$leftBusinessToday, 'ph-arrow-fat-left', ''],
        ['Today Right', '$'.$rightBusinessToday, 'ph-arrow-fat-right', ''],
        ['Overall Left', '$'.$leftBusinessTotal, 'ph-chart-bar', 'is-accent'],
        ['Overall Right', '$'.$rightBusinessTotal, 'ph-chart-bar', 'is-accent'],
        ['Today Referral', '$'.$referralToday, 'ph-users', 'is-warn'],
        ['Overall Referral', '$'.$referralTotal, 'ph-users-three', 'is-warn'],
        ['Customer ID', (string) $user->id, 'ph-identification-badge', ''],
        ['Package', '$'.number_format((float) ($user->package->amount ?? 0), 2), 'ph-package', ''],
    ] as [$label, $value, $icon, $tone])
    <div class="cmc-stat-card {{ $tone }} p-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-medium text-muted">{{ $label }}</p>
            <span class="cmc-stat-icon"><i class="ph {{ $icon }}"></i></span>
        </div>
        <p class="text-2xl font-bold text-heading tracking-tight">{{ $value }}</p>
    </div>
    @endforeach
</div>

<section class="cmc-panel mb-6">
    <div class="cmc-panel-head">
        <span class="cmc-stat-icon"><i class="ph ph-link"></i></span>
        <div>
            <h2 class="text-base font-semibold text-heading m-0">Share registration link</h2>
            <p class="text-xs text-muted m-0">New members enter placement ID. Sponsor can be included or left blank.</p>
        </div>
    </div>
    <div class="p-4 space-y-3">
        <div>
            <p class="text-xs font-medium text-text-secondary mb-1.5">Without sponsor</p>
            <div class="flex flex-col sm:flex-row gap-2">
                <input id="open-register-url" type="text" readonly value="{{ $openRegisterUrl }}" class="flex-1 h-10 px-3 rounded-xl bg-subtle border border-border text-xs text-text">
                <button type="button" data-copy="open-register-url" class="inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
                    <i class="ph ph-copy"></i> Copy
                </button>
            </div>
        </div>
        <div>
            <p class="text-xs font-medium text-text-secondary mb-1.5">With your sponsor ID</p>
            <div class="flex flex-col sm:flex-row gap-2">
                <input id="open-register-sponsored-url" type="text" readonly value="{{ $openRegisterSponsoredUrl }}" class="flex-1 h-10 px-3 rounded-xl bg-subtle border border-border text-xs text-text">
                <button type="button" data-copy="open-register-sponsored-url" class="inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
                    <i class="ph ph-copy"></i> Copy
                </button>
            </div>
        </div>
    </div>
</section>

<section class="cmc-panel">
    <div class="cmc-panel-head">
        <span class="cmc-stat-icon"><i class="ph ph-lightning"></i></span>
        <div>
            <h2 class="text-base font-semibold text-heading m-0">Quick actions</h2>
            <p class="text-xs text-muted m-0">Move through your crypto workspace faster</p>
        </div>
    </div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <a href="{{ route('customer.withdrawals.create') }}" class="cmc-quick-link">
            <span class="cmc-ql-icon"><i class="ph ph-hand-withdraw"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-heading">Request withdrawal</p>
                <p class="text-xs text-muted">Send USDT to your wallet</p>
            </div>
            <i class="ph ph-caret-right text-muted ml-auto"></i>
        </a>
        <a href="{{ route('customer.tree') }}" class="cmc-quick-link">
            <span class="cmc-ql-icon"><i class="ph ph-tree-structure"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-heading">Grow your tree</p>
                <p class="text-xs text-muted">Invite & place members</p>
            </div>
            <i class="ph ph-caret-right text-muted ml-auto"></i>
        </a>
        <a href="{{ route('customer.income.history') }}" class="cmc-quick-link">
            <span class="cmc-ql-icon"><i class="ph ph-chart-line-up"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-heading">Income ledger</p>
                <p class="text-xs text-muted">ROI, binary & referral</p>
            </div>
            <i class="ph ph-caret-right text-muted ml-auto"></i>
        </a>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const el = document.getElementById('cmc-server-clock');
    if (!el) return;
    const start = Number(el.dataset.serverMs);
    const pageStart = Date.now();
    const fmt = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Kuala_Lumpur',
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
    function tick() {
        const parts = fmt.formatToParts(new Date(start + (Date.now() - pageStart)));
        const get = (type) => parts.find((p) => p.type === type)?.value || '';
        el.textContent = get('weekday') + ', ' + get('day') + ' ' + get('month') + ' ' + get('year') + ' · ' + get('hour') + ':' + get('minute') + ':' + get('second') + ' ' + get('dayPeriod').toUpperCase();
    }
    tick();
    setInterval(tick, 1000);
})();

(function () {
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const input = document.getElementById(btn.getAttribute('data-copy') || '');
            if (!input) return;
            const value = input.value;
            try {
                await navigator.clipboard.writeText(value);
            } catch (err) {
                input.select();
                document.execCommand('copy');
            }
            const label = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-check"></i> Copied';
            setTimeout(() => { btn.innerHTML = label; }, 1500);
        });
    });
})();
</script>
@endpush
