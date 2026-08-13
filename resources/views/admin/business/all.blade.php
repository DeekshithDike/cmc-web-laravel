@extends('layouts.admin')
@section('title', 'All Users Business')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>All Users Business</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="date" name="date" value="{{ $date }}" class="form-control">
            <button class="btn btn-primary m-l-sm">Filter</button>
        </form>
        <table class="table table-striped">
            <thead><tr><th>User ID</th><th>Name</th><th>Left</th><th>Right</th><th>Total</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['user_id'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>${{ $row['left'] }}</td>
                    <td>${{ $row['right'] }}</td>
                    <td>${{ number_format((float)$row['left'] + (float)$row['right'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No business volume for this date.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
