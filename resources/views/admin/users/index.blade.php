@extends('layouts.admin')
@section('title', 'Active Users')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Active Users List</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search Customer ID / name / email / phone">
            <select name="package_id" class="form-control m-l-sm">
                <option value="">All packages</option>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}" @selected((int)$packageId === (int)$package->id)>{{ $package->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-default m-l-sm">Reset</a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-info m-l-sm">Add New User</a>
            <a href="{{ route('admin.users.export', array_filter(['q' => $q, 'package_id' => $packageId ?: null])) }}" class="btn btn-success m-l-sm">Download Excel (CSV)</a>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Customer ID</th><th>Name</th><th>Email</th><th>Package</th><th>Wallet</th><th>Expiry</th></tr></thead>
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
        @include('admin.partials.pagination', ['paginator' => $users])
    </div>
</div>
@endsection
