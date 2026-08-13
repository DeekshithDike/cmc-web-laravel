@extends('layouts.admin')
@section('title', 'Expired Users')
@section('heading', 'Manage Renewals — Expired Users')
@section('content')
<div class="card">
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Expiry</th></tr></thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->expiry_date?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No expired members.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $users->links() }}</div>
</div>
@endsection
