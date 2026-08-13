@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Start Manual Payment</h5></div>
    <div class="ibox-content">
        <form method="POST" action="{{ route('admin.payments.start') }}" class="form-inline m-b-md">
            @csrf
            <input class="form-control m-r-sm" type="number" name="user_id" placeholder="User ID" required>
            <input class="form-control m-r-sm" type="number" step="0.01" name="amount" placeholder="Amount" required>
            <button class="btn btn-primary">Create pending payment</button>
        </form>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Payment Transactions</h5></div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>User</th><th>Provider</th><th>Ref</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($transactions as $tx)
                <tr>
                    <td>{{ $tx->id }}</td>
                    <td>{{ $tx->user_id ? '#'.$tx->user_id : ($tx->meta['signup']['email'] ?? 'pending') }}</td>
                    <td>{{ $tx->provider?->value ?? $tx->provider }}</td>
                    <td>{{ $tx->provider_ref }}</td>
                    <td>${{ number_format((float)$tx->amount, 2) }}</td>
                    <td>
                        {{ $tx->status }}
                        @if (! empty($tx->meta['activation_error']))
                            <div class="text-danger small">{{ $tx->meta['activation_error'] }}</div>
                        @endif
                    </td>
                    <td>
                        @if($tx->status === 'pending')
                        <form method="POST" action="{{ route('admin.payments.confirm', $tx) }}">@csrf
                            <button class="btn btn-primary btn-sm">Confirm</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $transactions->links() }}
    </div>
</div>
@endsection
