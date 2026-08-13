@extends('layouts.admin')
@section('title', 'Renewed Users')
@section('heading', 'Manage Renewals — Renewed Users')
@section('content')
<div class="card">
    <table>
        <thead><tr><th>User</th><th>Previous</th><th>New Expiry</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->user?->name ?? $row->user_id }}</td>
                    <td>{{ $row->previous_expiry?->format('Y-m-d') }}</td>
                    <td>{{ $row->new_expiry?->format('Y-m-d') }}</td>
                    <td>${{ number_format((float) $row->amount, 2) }}</td>
                    <td>{{ $row->created_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No renewals yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $rows->links() }}</div>
</div>
@endsection
