@extends('layouts.customer')
@section('title', 'Withdrawal History')
@section('heading', 'Withdrawal History')
@section('content')
<div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-muted text-xs uppercase">
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
                <tr class="border-t border-border">
                    <td class="px-4 py-3 text-heading font-medium">${{ number_format((float)$item->amount, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format((float)$item->fee, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format((float)$item->payable_amount, 2) }}</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-lg bg-subtle text-xs font-medium">{{ $item->status->label() }}</span></td>
                    <td class="px-4 py-3 text-muted">{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No withdrawals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $withdrawals->links() }}</div>
</div>
@endsection
