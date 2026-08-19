@extends('layouts.admin')
@section('title', 'Renewed Users')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Manage Renewals — Renewed Users</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search Customer ID / name / email">
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.renewals.renewed') }}" class="btn btn-default m-l-sm">Reset</a>
        </form>
        <table class="table table-striped">
            <thead><tr><th>Customer</th><th>Previous</th><th>New Expiry</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>#{{ $row->user_id }} {{ $row->user?->name ?? '' }}</td>
                    <td>{{ \App\Support\IncomeCalendar::formatDate($row->previous_expiry) }}</td>
                    <td>{{ \App\Support\IncomeCalendar::formatDate($row->new_expiry) }}</td>
                    <td>${{ number_format((float) $row->amount, 2) }}</td>
                    <td>{{ \App\Support\IncomeCalendar::formatWhen($row->created_at) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No renewals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        @include('admin.partials.pagination', ['paginator' => $rows])
    </div>
</div>
@endsection
