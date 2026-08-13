@extends('layouts.admin')
@section('title', 'Daily Paid Income')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Run Daily Income</h5></div>
    <div class="ibox-content">
        <p class="m-b-sm">Calculates <strong>{{ $asOf }}</strong> (yesterday, 00:00–23:59). Same job as the 00:05 cron. Already calculated days are skipped.</p>
        @if($existing && $existing->status === 'completed')
            <p class="text-success">Already calculated: {{ $existing->processed }} members, ${{ number_format((float) $existing->total_paid, 2) }} ({{ $existing->triggered_by }}).</p>
        @endif
        <form method="POST" action="{{ route('admin.income.daily.run') }}">
            @csrf
            <button class="btn btn-primary" @if($existing && $existing->status === 'completed') disabled @endif>Run previous day income</button>
        </form>
        <p class="text-muted m-t-sm">Pays ROI (skipped Sunday and Monday), 5% binary matching (capped at package and $500), and 10% of that day's stored referral package volume.</p>
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
