@extends('layouts.admin')
@section('title', 'Active Users')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Active Users List</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search ID / name / email / phone">
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.users.create') }}" class="btn btn-info m-l-sm">Add New User</a>
            <a href="{{ route('admin.users.export') }}" class="btn btn-success m-l-sm">Download Excel (CSV)</a>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Package</th><th>Wallet</th><th>Expiry</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->package->name ?? '—' }}</td>
                        <td>${{ number_format((float)$user->wallet_balance, 2) }}</td>
                        <td>{{ $user->expiry_date?->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</div>
@endsection
