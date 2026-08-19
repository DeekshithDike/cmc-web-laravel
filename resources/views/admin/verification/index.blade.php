@extends('layouts.admin')
@section('title', 'Customer verification')
@section('content')
@php
    $money = static function ($value): string {
        if ($value === null) {
            return 'Pending';
        }

        return '$'.number_format((float) $value, 2);
    };
    $binaryPercent = rtrim(rtrim(number_format((float) config('citymax.income.binary_percent'), 2), '0'), '.');
    $referralPercent = rtrim(rtrim(number_format((float) config('citymax.income.referral_percent'), 2), '0'), '.');
@endphp

<div class="ibox">
    <div class="ibox-title"><h5>Customer verification</h5></div>
    <div class="ibox-content">
        <p class="text-muted m-b-md">Look up one customer. Customer ID is required for a full picture. Date and complaint type are optional. Dates are Malaysia calendar days. This page is read-only.</p>
        <form method="GET" action="{{ route('admin.verification.index') }}" class="form-inline m-b-sm">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Customer ID (required) / name / email" maxlength="100" autocomplete="off">
            <select name="range" class="form-control m-l-sm">
                <option value="all" @selected($filters->range === 'all')>All dates</option>
                <option value="today" @selected($filters->range === 'today')>Today</option>
                <option value="yesterday" @selected($filters->range === 'yesterday')>Yesterday</option>
                <option value="7d" @selected($filters->range === '7d')>Last 7 days</option>
                <option value="custom" @selected($filters->range === 'custom')>Custom dates</option>
            </select>
            <input type="date" name="from" value="{{ $filters->from }}" class="form-control m-l-sm" title="From date">
            <input type="date" name="to" value="{{ $filters->to }}" class="form-control m-l-sm" title="To date">
            <select name="focus" class="form-control m-l-sm">
                <option value="all" @selected($filters->focus === 'all')>Full picture</option>
                <option value="roi" @selected($filters->focus === 'roi')>ROI not received</option>
                <option value="binary" @selected($filters->focus === 'binary')>Binary not matched</option>
                <option value="business" @selected($filters->focus === 'business')>Left / right wrong</option>
                <option value="referral" @selected($filters->focus === 'referral')>Referral not added</option>
            </select>
            <select name="days" class="form-control m-l-sm">
                <option value="activity" @selected($filters->days === 'activity')>Days with activity</option>
                <option value="all" @selected($filters->days === 'all')>Every day</option>
                <option value="match" @selected($filters->days === 'match')>Match days only</option>
                <option value="zero" @selected($filters->days === 'zero')>Days with $0 income</option>
            </select>
            <button class="btn btn-primary m-l-sm">Look up</button>
            <a href="{{ route('admin.verification.index') }}" class="btn btn-default m-l-sm">Reset</a>
        </form>
        <p class="text-muted m-b-none">Custom dates apply when “Custom dates” is selected. Ledger, sources, and referrals are paginated.</p>
    </div>
</div>

@if($q !== '' && $report === null && $matches !== null && $matches->total() === 0)
    <div class="alert alert-warning">No customer matched “{{ $q }}”. Try the numeric Customer ID.</div>
@endif

@if($matches !== null && $matches->total() > 1)
    <div class="ibox">
        <div class="ibox-title"><h5>Select a customer</h5></div>
        <div class="ibox-content">
            <p class="text-muted">Several people matched. Open one ID to verify.</p>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Customer ID</th><th>Name</th><th>Email</th><th>Package</th><th></th></tr></thead>
                    <tbody>
                    @foreach($matches as $match)
                        <tr>
                            <td>{{ $match->id }}</td>
                            <td>{{ $match->name }}</td>
                            <td>{{ $match->email }}</td>
                            <td>{{ $match->package->name ?? '—' }}</td>
                            <td class="text-right">
                                <a class="btn btn-primary btn-sm" href="{{ route('admin.verification.index', array_merge(request()->except('page'), ['q' => $match->id])) }}">Verify</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pagination', ['paginator' => $matches])
        </div>
    </div>
@endif

@if($report)
    @php
        $user = $report->user;
        $eligible = $report->eligibility['eligible'];
        $alertClass = $report->answer['tone'] === 'success' ? 'alert-success' : ($report->answer['tone'] === 'warning' ? 'alert-warning' : 'alert-info');
    @endphp

    <div class="ibox">
        <div class="ibox-title">
            <h5>Customer #{{ $user->id }} · {{ $user->name }}</h5>
        </div>
        <div class="ibox-content">
            <span class="label {{ $eligible ? 'label-primary' : 'label-warning' }}" style="display:inline-block">
                {{ $eligible ? 'Eligible to be paid' : 'Not eligible' }}
            </span>
            <dl class="cmc-readonly-dl m-t-md">
                <dt>Package</dt>
                <dd>{{ $user->package->name ?? '—' }} · ${{ number_format((float) ($user->package->amount ?? 0), 2) }} · {{ rtrim(rtrim(number_format((float) ($user->package->roi_percent ?? 0), 2), '0'), '.') }}% ROI</dd>
                <dt>Wallet</dt>
                <dd>${{ number_format((float) $user->wallet_balance, 2) }}</dd>
                <dt>Expiry</dt>
                <dd>{{ \App\Support\IncomeCalendar::formatDate($user->expiry_date) }}{{ $report->eligibility['expired'] ? ' (expired)' : '' }}</dd>
                <dt>Status</dt>
                <dd>{{ $report->eligibility['active'] ? 'Active' : 'Inactive' }} · {{ $report->eligibility['paid'] ? 'paid' : 'unpaid' }}</dd>
                <dt>Sponsor (referral)</dt>
                <dd>{{ $user->sponsor_id ? '#'.$user->sponsor_id.($user->sponsor?->name ? ' '.$user->sponsor->name : '') : '—' }}</dd>
                <dt>Placement (business)</dt>
                <dd>{{ $user->parent_id ? '#'.$user->parent_id.($user->parent?->name ? ' '.$user->parent->name : '') : '—' }}{{ $user->position ? ' · '.$user->position->value : '' }}</dd>
                <dt>Binary rule</dt>
                <dd>{{ $binaryPercent }}% of weaker side · cap ${{ number_format((float) ($user->package->amount ?? 0), 2) }}</dd>
                <dt>Activated date</dt>
                <dd>{{ \App\Support\IncomeCalendar::formatWhen($user->created_at) }}</dd>
            </dl>
        </div>
    </div>

    <div class="alert {{ $alertClass }}">{{ $report->answer['text'] }}</div>

    <div class="ibox">
        <div class="ibox-title"><h5>Period totals</h5></div>
        <div class="ibox-content">
            <p class="text-muted">Paid income excludes today until the midnight job runs. Carry now: Left ${{ $report->carryNow['left'] }} / Right ${{ $report->carryNow['right'] }}.</p>
            <div class="row text-center">
                <div class="col-sm-4 m-b-md"><div class="font-bold">{{ $money($report->totals['roi']) }}</div><div class="text-muted">ROI paid</div></div>
                <div class="col-sm-4 m-b-md"><div class="font-bold">{{ $money($report->totals['binary']) }}</div><div class="text-muted">Binary paid</div></div>
                <div class="col-sm-4 m-b-md"><div class="font-bold">{{ $money($report->totals['referral']) }}</div><div class="text-muted">Referral paid</div></div>
                <div class="col-sm-4 m-b-md"><div class="font-bold">{{ $money($report->totals['left']) }}</div><div class="text-muted">Left business</div></div>
                <div class="col-sm-4 m-b-md"><div class="font-bold">{{ $money($report->totals['right']) }}</div><div class="text-muted">Right business</div></div>
                <div class="col-sm-4 m-b-md"><div class="font-bold">{{ $money($report->totals['matched']) }} · {{ $report->totals['match_days'] }} days</div><div class="text-muted">Matched business</div></div>
            </div>
        </div>
    </div>

    <div class="ibox">
        <div class="ibox-title"><h5>Day-wise ledger</h5></div>
        <div class="ibox-content">
            <p class="text-muted m-b-sm">Each line is one day. Read the columns like this:</p>
            <ul class="text-muted m-b-md" style="padding-left:18px">
                <li><strong>Left / Right</strong> — new business that came in on that day.</li>
                <li><strong>Matched</strong> — how much left and right could pair for binary pay. Both sides must have business. If one side is $0.00, matched is $0.00 even if the other side is large.</li>
                <li><strong>Carry L / R</strong> — leftover business that was not paired, carried to the next day.</li>
                <li><strong>ROI / Binary $ / Referral / Paid</strong> — money already credited to the wallet. <strong>Pending</strong> means today; it is paid after midnight Malaysia time.</li>
            </ul>
            <div class="table-responsive">
                <table class="table table-striped table-condensed">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Left</th>
                        <th class="text-right">Right</th>
                        <th class="text-right">Matched</th>
                        <th class="text-right">Carry L / R</th>
                        <th class="text-right">ROI</th>
                        <th class="text-right">Binary $</th>
                        <th class="text-right">Referral</th>
                        <th class="text-right">Paid</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($report->ledger as $row)
                        <tr>
                            <td>{{ \App\Support\IncomeCalendar::formatDate($row['date']) }} {{ $row['day'] }}</td>
                            <td class="text-right">{{ $money($row['left']) }}</td>
                            <td class="text-right">{{ $money($row['right']) }}</td>
                            <td class="text-right">{{ $money($row['matched']) }}</td>
                            <td class="text-right">${{ $row['carry_left'] }} / ${{ $row['carry_right'] }}</td>
                            <td class="text-right">{{ $money($row['roi']) }}</td>
                            <td class="text-right">{{ $money($row['binary']) }}</td>
                            <td class="text-right">{{ $money($row['referral']) }}</td>
                            <td class="text-right">{{ $money($row['paid']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No days in this range for the selected day filter.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pagination', ['paginator' => $report->ledger])
        </div>
    </div>

    @if($report->sources !== null)
        <div class="ibox">
            <div class="ibox-title"><h5>Left / Right breakdown</h5></div>
            <div class="ibox-content">
                <p class="text-muted m-b-sm">This is the detail behind Left and Right in the ledger. Each row is one downline whose package was added to this customer.</p>
                <ul class="text-muted m-b-md" style="padding-left:18px">
                    <li><strong>Activated date</strong> — when that downline paid and joined (time shown if saved).</li>
                    <li><strong>Added to</strong> — this customer’s Left or Right, based on where that ID sits.</li>
                    <li><strong>Downline ID / Name</strong> — who joined.</li>
                    <li><strong>Package amount</strong> — dollars added to that side (not “1 person”). People this customer sponsored appear here only if they also sit on this customer’s Left or Right.</li>
                </ul>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Activated date</th>
                            <th>Added to</th>
                            <th>Downline ID</th>
                            <th>Name</th>
                            <th class="text-right">Package amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($report->sources as $source)
                            <tr>
                                <td>{{ \App\Support\IncomeCalendar::formatWhen($source->from_activated_at ?? null, $source->source_date) }}</td>
                                <td>{{ $source->side }}</td>
                                <td>{{ $source->from_user_id ? '#'.$source->from_user_id : '—' }}</td>
                                <td>{{ $source->from_name ?: '—' }}</td>
                                <td class="text-right">{{ $money($source->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No Left or Right business in this date range.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['paginator' => $report->sources])
            </div>
        </div>
    @endif

    @if($report->referrals !== null)
        <div class="ibox">
            <div class="ibox-title"><h5>Referral breakdown</h5></div>
            <div class="ibox-content">
                <p class="text-muted m-b-sm">This lists people who joined with this customer as <strong>sponsor</strong>. It is not Left/Right tree business. This customer does not get the full package; they get {{ $referralPercent }}% after the daily income run.</p>
                <ul class="text-muted m-b-md" style="padding-left:18px">
                    <li><strong>Activated date</strong> — when the referred person paid and joined (time shown if saved).</li>
                    <li><strong>Referred ID / Name</strong> — who used this customer’s referral.</li>
                    <li><strong>Package amount</strong> — that person’s package (not paid in full to this customer).</li>
                    <li><strong>Referral ({{ $referralPercent }}%)</strong> — amount this customer should receive.</li>
                    <li><strong>Pay status</strong> — already credited, or waiting for the midnight job.</li>
                </ul>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Activated date</th>
                            <th>Referred ID</th>
                            <th>Name</th>
                            <th class="text-right">Package amount</th>
                            <th class="text-right">Referral ({{ $referralPercent }}%)</th>
                            <th>Pay status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($report->referrals as $referral)
                            <tr>
                                <td>{{ $referral['activated_at'] }}</td>
                                <td>{{ $referral['from_id'] ? '#'.$referral['from_id'] : '—' }}</td>
                                <td>{{ $referral['from_name'] ?: '—' }}</td>
                                <td class="text-right">{{ $money($referral['stored']) }}</td>
                                <td class="text-right">{{ $money($referral['expected']) }}</td>
                                <td>{{ $referral['status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No one joined with this customer as sponsor in this date range.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['paginator' => $report->referrals])
            </div>
        </div>
    @endif
@endif
@endsection
