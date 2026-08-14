@extends('layouts.admin')
@section('title', 'Active Renewals')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Manage Renewals — Active Users</h5></div>
    <div class="ibox-content">
        <p class="text-muted">Renew Now is available within {{ $warningDays }} days of expiry.</p>
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search Customer ID / name / email / phone">
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.renewals.active') }}" class="btn btn-default m-l-sm">Reset</a>
        </form>
        <table class="table table-striped">
            <thead><tr><th>Customer ID</th><th>Name</th><th>Package</th><th>Expiry</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                @php $days = $user->expiry_date ? now()->startOfDay()->diffInDays($user->expiry_date, false) : null; @endphp
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->package->name ?? '—' }}</td>
                    <td>{{ $user->expiry_date?->format('Y-m-d') }} @if(!is_null($days) && $days>=0) ({{ $days }}d) @endif</td>
                    <td>
                        @if(!is_null($days) && $days <= $warningDays)
                        <form method="POST" action="{{ route('admin.renewals.renew', $user->id) }}">@csrf
                            <button class="btn btn-primary btn-sm">Renew Now</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No active members.</td></tr>
            @endforelse
            </tbody>
        </table>
        @include('admin.partials.pagination', ['paginator' => $users])
    </div>
</div>
@endsection
