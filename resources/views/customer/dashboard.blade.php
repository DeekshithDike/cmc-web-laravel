@extends('layouts.customer')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
@if ($showExpiryWarning)
    <div class="mb-4 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm">
        Your membership expires in {{ $daysLeft }} day(s) on {{ $user->expiry_date?->format('Y-m-d') }}.
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ([
        ['Your ID', $user->id, 'ph-identification-badge'],
        ['Wallet', '$'.number_format((float)$user->wallet_balance, 2), 'ph-wallet'],
        ['Package', '$'.number_format((float)($user->package->amount ?? 0), 2), 'ph-package'],
        ['Expiry', $user->expiry_date?->format('Y-m-d') ?? '—', 'ph-calendar'],
        ['Today Left', '$'.$leftBusinessToday, 'ph-arrow-fat-left'],
        ['Today Right', '$'.$rightBusinessToday, 'ph-arrow-fat-right'],
        ['Overall Left', '$'.$leftBusinessTotal, 'ph-chart-bar'],
        ['Overall Right', '$'.$rightBusinessTotal, 'ph-chart-bar'],
        ['Today Referral', '$'.$referralToday, 'ph-users'],
        ['Overall Referral', '$'.$referralTotal, 'ph-users-three'],
    ] as [$label, $value, $icon])
    <div class="bg-surface border border-border rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-medium text-muted">{{ $label }}</p>
            <i class="ph {{ $icon }} text-xl text-primary"></i>
        </div>
        <p class="text-2xl font-bold text-heading">{{ $value }}</p>
    </div>
    @endforeach
</div>
@endsection
