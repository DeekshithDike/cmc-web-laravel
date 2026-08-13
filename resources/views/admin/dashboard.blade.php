@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    @foreach ([
        'Active Users' => $stats['active_users'],
        "Today's Users" => $stats['today_users'],
        'Power ID' => $stats['power_ids'],
        'Withdrawal Requests' => $stats['pending_withdrawals'],
        'Total Business' => '$'.$stats['total_business'],
        "Today's Business" => '$'.$stats['today_business'],
        'Total Withdrawal' => '$'.$stats['total_withdrawal'],
        "Today's Withdrawal" => '$'.$stats['today_withdrawal'],
    ] as $label => $value)
    <div class="col-lg-3">
        <div class="ibox">
            <div class="ibox-title"><h5>{{ $label }}</h5></div>
            <div class="ibox-content"><h1 class="no-margins">{{ $value }}</h1></div>
        </div>
    </div>
    @endforeach
</div>
@endsection
