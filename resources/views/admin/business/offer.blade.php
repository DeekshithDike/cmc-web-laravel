@extends('layouts.admin')
@section('title', 'Offer Business')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Offer Business</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="date" name="from" value="{{ $from }}" class="form-control">
            <input type="date" name="to" value="{{ $to }}" class="form-control m-l-sm">
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
                <tr><td colspan="5">No business in this range.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
