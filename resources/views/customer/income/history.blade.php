@extends('layouts.customer')
@section('title', 'Income History')
@section('heading', 'Income History')
@section('content')
<div class="cmc-panel">
    <div class="cmc-panel-head">
        <span class="cmc-stat-icon"><i class="ph ph-chart-line-up"></i></span>
        <div>
            <h2 class="text-base font-semibold text-heading m-0">Income ledger</h2>
            <p class="text-xs text-muted m-0">Daily ROI, binary matching & referral bonuses</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-primary/5 text-muted text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-3">Date</th>
                <th class="text-left px-4 py-3">ROI</th>
                <th class="text-left px-4 py-3">Binary</th>
                <th class="text-left px-4 py-3">Referral</th>
                <th class="text-left px-4 py-3">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr class="border-t border-border hover:bg-primary/5 transition-colors">
                    <td class="px-4 py-3 text-heading font-medium">{{ $row->paid_on?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3">${{ number_format((float) $row->roi_amount, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format((float) $row->binary_amount, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format((float) $row->referral_amount, 2) }}</td>
                    <td class="px-4 py-3 font-semibold text-primary">${{ number_format((float) $row->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center">
                        <span class="cmc-stat-icon mx-auto mb-3"><i class="ph ph-chart-line-up"></i></span>
                        <p class="text-muted">No income yet.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-border">{{ $rows->links() }}</div>
</div>
@endsection
