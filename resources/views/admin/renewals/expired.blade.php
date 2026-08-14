@extends('layouts.admin')
@section('title', 'Expired Users')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Manage Renewals — Expired Users</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search Customer ID / name / email / phone">
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.renewals.expired') }}" class="btn btn-default m-l-sm">Reset</a>
        </form>
        <table class="table table-striped">
            <thead><tr><th>Customer ID</th><th>Name</th><th>Expiry</th></tr></thead>
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
        @include('admin.partials.pagination', ['paginator' => $users])
    </div>
</div>
@endsection
