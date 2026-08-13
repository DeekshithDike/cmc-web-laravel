@extends('layouts.admin')
@section('title', 'Withdrawals')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Withdrawals — {{ $status->label() }}</h5>
        @if($status->value === 'completed')
            <a href="{{ route('admin.withdrawals.export.completed') }}" class="btn btn-success btn-sm pull-right">Download Excel (CSV)</a>
        @endif
    </div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead>
            <tr>
                <th>ID</th><th>User</th><th>Amount</th><th>Fee</th><th>Payable</th><th>Wallet</th><th>Payout</th><th>Date</th>
                @if($status->value === 'pending')<th>Action</th>@endif
            </tr>
            </thead>
            <tbody>
            @forelse($withdrawals as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>#{{ $item->user_id }} {{ $item->user->name ?? '' }}</td>
                    <td>${{ number_format((float)$item->amount, 2) }}</td>
                    <td>${{ number_format((float)$item->fee, 2) }}</td>
                    <td>${{ number_format((float)$item->payable_amount, 2) }}</td>
                    <td class="text-break">{{ $item->wallet_address }}</td>
                    <td>{{ $item->payout_provider ?? '—' }}@if($item->payout_ref)<br><small class="text-muted">{{ $item->payout_ref }}</small>@endif</td>
                    <td>{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                    @if($status->value === 'pending')
                    <td>
                        <form method="POST" action="{{ route('admin.withdrawals.complete', $item) }}" style="display:inline">@csrf
                            <button class="btn btn-primary btn-sm">Pay Now</button>
                        </form>
                        <form method="POST" action="{{ route('admin.withdrawals.decline', $item) }}" style="display:inline">@csrf
                            <button class="btn btn-danger btn-sm">Decline</button>
                        </form>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="8">No records.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $withdrawals->links() }}
    </div>
</div>
@endsection
