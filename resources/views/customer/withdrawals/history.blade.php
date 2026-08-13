@extends('layouts.customer')
@section('title', 'Withdrawal History')
@section('heading', 'Withdrawal History')
@section('content')
<div class="cmc-panel">
    <div class="cmc-panel-head">
        <span class="cmc-stat-icon"><i class="ph ph-clock-counter-clockwise"></i></span>
        <div>
            <h2 class="text-base font-semibold text-heading m-0">Payout history</h2>
            <p class="text-xs text-muted m-0">Track every USDT withdrawal request</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-primary/5 text-muted text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-3">Amount</th>
                <th class="text-left px-4 py-3">Fee</th>
                <th class="text-left px-4 py-3">Payable</th>
                <th class="text-left px-4 py-3">Status</th>
                <th class="text-left px-4 py-3">Date</th>
            </tr>
            </thead>
            <tbody>
            @forelse($withdrawals as $item)
                <tr class="border-t border-border hover:bg-primary/5 transition-colors">
                    <td class="px-4 py-3 text-heading font-medium">${{ number_format((float) $item->amount, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format((float) $item->fee, 2) }}</td>
                    <td class="px-4 py-3 font-semibold text-primary">${{ number_format((float) $item->payable_amount, 2) }}</td>
                    <td class="px-4 py-3"><span class="cmc-chip !normal-case tracking-normal">{{ $item->status->label() }}</span></td>
                    <td class="px-4 py-3 text-muted">{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center">
                        <span class="cmc-stat-icon mx-auto mb-3"><i class="ph ph-hand-withdraw"></i></span>
                        <p class="text-muted">No withdrawals yet.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-border">{{ $withdrawals->links() }}</div>
</div>
@endsection
