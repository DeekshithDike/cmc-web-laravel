@extends('layouts.admin')
@section('title', 'Daily Paid Income')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Run Daily Income</h5></div>
    <div class="ibox-content">
        <form method="POST" action="{{ route('admin.income.daily.run') }}" class="form-inline">
            @csrf
            <input class="form-control m-r-sm" type="date" name="as_of" value="{{ now()->toDateString() }}">
            <button class="btn btn-primary">Run ROI payout</button>
        </form>
        <p class="text-muted m-t-sm">Credits package ROI % to each active member once per day. Binary/referral remain for Node calc later.</p>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Daily Paid Income</h5></div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead><tr><th>Date</th><th>User</th><th>ROI</th><th>Binary</th><th>Referral</th><th>Total</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->paid_on?->format('Y-m-d') }}</td>
                    <td>#{{ $row->user_id }} {{ $row->user->name ?? '' }}</td>
                    <td>${{ number_format((float)$row->roi_amount, 2) }}</td>
                    <td>${{ number_format((float)$row->binary_amount, 2) }}</td>
                    <td>${{ number_format((float)$row->referral_amount, 2) }}</td>
                    <td>${{ number_format((float)$row->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No income rows yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $rows->links() }}
    </div>
</div>
@endsection
