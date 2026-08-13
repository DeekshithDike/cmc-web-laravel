@extends('layouts.customer')
@section('title', 'Income History')
@section('heading', 'Income History')
@section('content')
<div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-muted text-xs uppercase">
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
                <tr class="border-t border-border">
                    <td class="px-4 py-3">{{ $row->paid_on?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3">${{ number_format((float)$row->roi_amount, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format((float)$row->binary_amount, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format((float)$row->referral_amount, 2) }}</td>
                    <td class="px-4 py-3 font-semibold text-heading">${{ number_format((float)$row->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No income yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $rows->links() }}</div>
</div>
@endsection
