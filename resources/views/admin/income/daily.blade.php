@extends('layouts.admin')
@php
    $alreadyDone = $existing && $existing->status === 'completed';
    $asOfDay = \Illuminate\Support\Carbon::parse($asOf, config('citymax.income.timezone'))->format('l');
    $paysRoi = \App\Support\IncomeCalendar::paysRoiOn($asOf);
    $binaryPercent = rtrim(rtrim(number_format((float) config('citymax.income.binary_percent'), 2), '0'), '.');
    $referralPercent = rtrim(rtrim(number_format((float) config('citymax.income.referral_percent'), 2), '0'), '.');
@endphp
@section('title', 'Daily Paid Income')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Run Daily Income</h5></div>
    <div class="ibox-content">
        <p class="m-b-sm">Calculates <strong>{{ $asOf }}</strong> (yesterday Malaysia time, 00:00–23:59). Same job as the {{ config('citymax.income.run_at') }} Malaysia cron. Already calculated days are skipped.</p>
        @if($alreadyDone)
            <p class="text-success">Already calculated: {{ $existing->processed }} members, ${{ number_format((float) $existing->total_paid, 2) }} ({{ $existing->triggered_by }}).</p>
        @endif
        <button
            type="button"
            class="btn btn-primary{{ $alreadyDone ? '' : ' js-income-run' }}"
            @if($alreadyDone) disabled @endif
        >Run previous day income</button>
        <p class="text-muted m-t-sm">Pays ROI Monday–Friday (Saturday and Sunday skipped), {{ $binaryPercent }}% binary matching (capped at activated package amount), and {{ $referralPercent }}% of that day's stored referral package volume.</p>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Daily Paid Income</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search Customer ID / name / email">
            <input type="date" name="from" value="{{ $from }}" class="form-control m-l-sm">
            <input type="date" name="to" value="{{ $to }}" class="form-control m-l-sm">
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.income.daily') }}" class="btn btn-default m-l-sm">Reset</a>
        </form>
        <table class="table table-striped">
            <thead><tr><th>Date</th><th>Customer</th><th>ROI</th><th>Binary</th><th>Referral</th><th>Total</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->paid_on?->format('Y-m-d') }}</td>
                    <td>#{{ $row->user_id }} {{ $row->user->name ?? '' }}</td>
                    <td>${{ number_format((float)$row->roi_amount, 2) }}</td>
                    <td>${{ number_format((float)$row->binary_amount, 2) }}</td>
                    <td>${{ number_format((float)$row->referral_amount, 2) }}</td>
                    <td>${{ number_format((float)$row->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No income rows yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        @include('admin.partials.pagination', ['paginator' => $rows])
    </div>
</div>

@if(! $alreadyDone)
<div class="modal fade" id="incomeRunModal" tabindex="-1" role="dialog" aria-labelledby="incomeRunTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.income.daily.run') }}" id="incomeRunForm">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="incomeRunTitle">Run yesterday's income?</h4>
                </div>
                <div class="modal-body">
                    <p class="m-b-md">You are about to credit member wallets for <strong>{{ $asOf }}</strong> ({{ $asOfDay }}). That is yesterday in Malaysia time, the same job as the {{ config('citymax.income.run_at') }} Malaysia cron.</p>
                    <table class="table table-bordered table-condensed m-b-md">
                        <tbody>
                        <tr><th style="width:35%">Income date</th><td>{{ $asOf }} ({{ $asOfDay }})</td></tr>
                        <tr><th>Covers</th><td>Malaysia 00:00–23:59 for that day</td></tr>
                        <tr><th>Who is paid</th><td>Active paid members whose package is still valid on this date</td></tr>
                        <tr>
                            <th>ROI</th>
                            <td>
                                @if($paysRoi)
                                    Paid at each package's daily percent (Monday–Friday)
                                @else
                                    Not paid — {{ $asOfDay }} is a weekend
                                @endif
                            </td>
                        </tr>
                        <tr><th>Binary matching</th><td>{{ $binaryPercent }}% of matched left/right volume, capped at the member's package amount</td></tr>
                        <tr><th>Referral</th><td>{{ $referralPercent }}% of that day's stored referral package volume</td></tr>
                        </tbody>
                    </table>
                    <p class="m-b-md font-bold">This credits wallets and cannot be undone from this screen. If this day was already calculated, nothing extra is paid.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="incomeRunSubmit">Yes, run yesterday's income</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@if(! $alreadyDone)
@push('scripts')
<script>
(function ($) {
    var $modal = $('#incomeRunModal');
    var $form = $('#incomeRunForm');
    var $submit = $('#incomeRunSubmit');
    var submitting = false;

    $('.js-income-run').on('click', function () {
        $submit.prop('disabled', false).text("Yes, run yesterday's income");
        submitting = false;
        $modal.modal('show');
    });

    $form.on('submit', function () {
        if (submitting) {
            return false;
        }
        submitting = true;
        $submit.prop('disabled', true).text('Please wait…');
        return true;
    });
})(jQuery);
</script>
@endpush
@endif
