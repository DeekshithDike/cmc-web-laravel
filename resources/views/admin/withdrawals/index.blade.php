@extends('layouts.admin')
@section('title', 'Withdrawals')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Withdrawals — {{ $status->label() }}</h5>
        @if($status->value === 'completed')
            <a href="{{ route('admin.withdrawals.export.completed', array_filter(['q' => $q ?? ''])) }}" class="btn btn-success btn-sm pull-right">Download Excel (CSV)</a>
        @endif
        @if(! empty($showPayoutSync))
            <button type="button" class="btn btn-primary btn-sm pull-right js-wd-sync">Sync payout status</button>
        @endif
    </div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search Withdrawal ID / Customer ID / wallet / payout ref">
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.withdrawals.index', $status->value) }}" class="btn btn-default m-l-sm">Reset</a>
        </form>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>ID</th><th>Customer</th><th>Amount</th><th>Fee</th><th>Payable</th><th>Wallet</th><th>Payout</th><th>Date</th><th>Remarks</th>
                @if($status->value === 'pending')<th>Action</th>@endif
            </tr>
            </thead>
            <tbody>
            @forelse($withdrawals as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>#{{ $item->user_id }} {{ $item->user->name ?? '' }}</td>
                    <td>${{ number_format((float)$item->amount, 2) }}</td>
                    <td>${{ number_format((float)$item->fee, 2) }}</td>
                    <td>${{ number_format((float)$item->payable_amount, 2) }}</td>
                    <td class="text-break">{{ $item->wallet_address }}<br><small class="text-muted">{{ \App\Support\UsdtWalletAddress::label(\App\Support\UsdtWalletAddress::network((string) $item->wallet_address) ?? ($item->meta['network'] ?? null)) }}</small></td>
                    <td>{{ $item->payout_provider ?? '—' }}@if($item->payout_ref)<br><small class="text-muted">{{ $item->payout_ref }}</small>@endif</td>
                    <td>{{ \App\Support\IncomeCalendar::formatWhen($item->created_at) }}</td>
                    <td class="text-break">{{ filled($item->remarks) ? $item->remarks : '—' }}</td>
                    @if($status->value === 'pending')
                    @php
                        $networkLabel = \App\Support\UsdtWalletAddress::label(
                            \App\Support\UsdtWalletAddress::network((string) $item->wallet_address) ?? ($item->meta['network'] ?? null)
                        );
                    @endphp
                    <td>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm js-wd-confirm"
                            data-mode="pay"
                            data-action="{{ route('admin.withdrawals.complete', $item) }}"
                            data-id="{{ $item->id }}"
                            data-user="#{{ $item->user_id }} {{ $item->user->name ?? '' }}"
                            data-amount="${{ number_format((float)$item->amount, 2) }}"
                            data-fee="${{ number_format((float)$item->fee, 2) }}"
                            data-payable="${{ number_format((float)$item->payable_amount, 2) }}"
                            data-wallet="{{ $item->wallet_address }}"
                            data-network="{{ $networkLabel }}"
                            data-date="{{ \App\Support\IncomeCalendar::formatWhen($item->created_at) }}"
                        >Pay Now</button>
                        <button
                            type="button"
                            class="btn btn-danger btn-sm js-wd-confirm"
                            data-mode="decline"
                            data-action="{{ route('admin.withdrawals.decline', $item) }}"
                            data-id="{{ $item->id }}"
                            data-user="#{{ $item->user_id }} {{ $item->user->name ?? '' }}"
                            data-amount="${{ number_format((float)$item->amount, 2) }}"
                            data-fee="${{ number_format((float)$item->fee, 2) }}"
                            data-payable="${{ number_format((float)$item->payable_amount, 2) }}"
                            data-wallet="{{ $item->wallet_address }}"
                            data-network="{{ $networkLabel }}"
                            data-date="{{ \App\Support\IncomeCalendar::formatWhen($item->created_at) }}"
                        >Decline</button>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $status->value === 'pending' ? 10 : 9 }}">No records.</td></tr>
            @endforelse
            </tbody>
        </table>
        @include('admin.partials.pagination', ['paginator' => $withdrawals])
    </div>
</div>

@if($status->value === 'pending')
<div class="modal fade" id="wdConfirmModal" tabindex="-1" role="dialog" aria-labelledby="wdConfirmTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="wdConfirmForm">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="wdConfirmTitle">Confirm action</h4>
                </div>
                <div class="modal-body">
                    <div id="wdStepReview">
                        <p id="wdConfirmLead" class="m-b-md"></p>
                        <table class="table table-bordered table-condensed m-b-md">
                            <tbody>
                            <tr><th style="width:35%">Withdrawal ID</th><td id="wdDetailId"></td></tr>
                            <tr><th>Customer</th><td id="wdDetailUser"></td></tr>
                            <tr><th>Requested amount</th><td id="wdDetailAmount"></td></tr>
                            <tr><th>Fee</th><td id="wdDetailFee"></td></tr>
                            <tr><th>Payable</th><td id="wdDetailPayable"></td></tr>
                            <tr><th>Wallet address</th><td id="wdDetailWallet" class="text-break"></td></tr>
                            <tr><th>Network</th><td id="wdDetailNetwork"></td></tr>
                            <tr><th>Requested at</th><td id="wdDetailDate"></td></tr>
                            </tbody>
                        </table>
                        <div id="wdPayMethodWrap" class="m-b-md" style="display:none">
                            <p class="font-bold m-b-sm">How do you want to pay this?</p>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="payout_method" value="nowpayments" checked>
                                    <strong>Send via NOWPayments</strong>
                                    <div class="text-muted">Sends the payable amount to this wallet now.</div>
                                </label>
                            </div>
                            <div class="radio m-b-none">
                                <label>
                                    <input type="radio" name="payout_method" value="manual">
                                    <strong>Already paid outside</strong>
                                    <div class="text-muted">Use only if you already sent the USDT yourself. This will not send any crypto.</div>
                                </label>
                            </div>
                        </div>
                        <p id="wdConfirmAsk" class="m-b-md font-bold"></p>
                        <div class="form-group m-b-none" id="wdRemarksWrap">
                            <label for="wdRemarks">Remarks <span class="text-muted">(optional)</span></label>
                            <input type="text" class="form-control" name="remarks" id="wdRemarks" maxlength="255" placeholder="Optional note for this action">
                        </div>
                    </div>
                    <div id="wdStepFinal" style="display:none">
                        <p id="wdFinalLead" class="m-b-md"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" id="wdConfirmCancel">Cancel</button>
                    <button type="button" class="btn btn-default" id="wdConfirmBack" style="display:none">Back</button>
                    <button type="submit" class="btn" id="wdConfirmSubmit">Yes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(! empty($showPayoutSync))
<div class="modal fade" id="wdSyncModal" tabindex="-1" role="dialog" aria-labelledby="wdSyncTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.withdrawals.sync-processing') }}" id="wdSyncForm">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="wdSyncTitle">Sync payout status?</h4>
                </div>
                <div class="modal-body">
                    <p class="m-b-md">This checks NOWPayments for every <strong>processing</strong> withdrawal. It does <strong>not</strong> send money again.</p>
                    <ul class="m-b-md">
                        <li>If NOWPayments says finished, the withdrawal is marked <strong>completed</strong>.</li>
                        <li>If NOWPayments says failed, it is marked <strong>declined</strong> and the <strong>full amount is refunded</strong>.</li>
                        <li>If it is still sending, it stays in processing.</li>
                    </ul>
                    <p class="m-b-none font-bold">Use this only if the webhook did not update the list. Already completed or declined withdrawals are not changed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="wdSyncSubmit">Yes, sync status</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@if($status->value === 'pending')
@push('scripts')
<script>
(function ($) {
    var $modal = $('#wdConfirmModal');
    var $form = $('#wdConfirmForm');
    var $submit = $('#wdConfirmSubmit');
    var $back = $('#wdConfirmBack');
    var submitting = false;
    var mode = null;
    var payStep = 1;

    function isManualPay() {
        return mode === 'pay' && $('input[name="payout_method"]:checked').val() === 'manual';
    }

    function showReviewStep() {
        payStep = 1;
        $('#wdStepReview').show();
        $('#wdStepFinal').hide();
        $back.hide().prop('disabled', false);
        $('#wdConfirmCancel').show();
        $submit.prop('disabled', false);
        if (mode === 'pay') {
            var manual = isManualPay();
            $('#wdPayMethodWrap').show();
            $('#wdRemarksWrap').toggle(!manual);
            $submit
                .removeClass('btn-primary btn-danger btn-warning')
                .addClass(manual ? 'btn-warning' : 'btn-primary')
                .text(manual ? 'Continue to mark as already paid' : 'Continue to send via NOWPayments');
            $('#wdConfirmTitle').text('Pay this withdrawal?');
            $('#wdConfirmLead').html(
                'Review this withdrawal, then choose how to pay it.'
            );
            $('#wdConfirmAsk').text('Continue only after you have checked the wallet and amount.');
        } else {
            $('#wdPayMethodWrap').hide();
            $('#wdRemarksWrap').show();
            $submit
                .removeClass('btn-primary btn-danger btn-warning')
                .addClass('btn-danger')
                .text('Yes, decline');
            $('#wdConfirmTitle').text('Decline this withdrawal?');
            $('#wdConfirmLead').html(
                'You are about to <strong>decline</strong> this withdrawal. The full requested amount will be refunded to the member wallet balance.'
            );
            $('#wdConfirmAsk').text(
                'This cannot be undone from this screen. Continue only if you intend to reject this request now.'
            );
        }
    }

    function showFinalStep() {
        payStep = 2;
        var manual = isManualPay();
        $('#wdStepReview').hide();
        $('#wdStepFinal').show();
        $back.show();
        $('#wdConfirmTitle').text('Final confirmation');
        $('#wdFinalLead').html(
            manual
                ? 'This will <strong>mark the withdrawal as paid</strong>. No crypto will be sent and the member wallet will not be refunded. Use only if you already paid this amount outside.'
                : 'This will <strong>send the payable amount through NOWPayments</strong> to this wallet. This cannot be undone from this screen.'
        );
        $submit
            .removeClass('btn-primary btn-danger btn-warning')
            .addClass(manual ? 'btn-warning' : 'btn-primary')
            .prop('disabled', false)
            .text(manual ? 'Yes, mark as already paid' : 'Yes, send via NOWPayments');
    }

    $('.js-wd-confirm').on('click', function () {
        var $btn = $(this);
        mode = $btn.data('mode');

        $form.attr('action', $btn.data('action'));
        $('#wdRemarks').val('');
        $('input[name="payout_method"][value="nowpayments"]').prop('checked', true);

        $('#wdDetailId').text($btn.data('id'));
        $('#wdDetailUser').text($btn.data('user'));
        $('#wdDetailAmount').text($btn.data('amount'));
        $('#wdDetailFee').text($btn.data('fee'));
        $('#wdDetailPayable').text($btn.data('payable'));
        $('#wdDetailWallet').text($btn.data('wallet'));
        $('#wdDetailNetwork').text($btn.data('network') || '—');
        $('#wdDetailDate').text($btn.data('date'));

        submitting = false;
        showReviewStep();
        $modal.modal('show');
    });

    $('input[name="payout_method"]').on('change', function () {
        if (mode === 'pay' && payStep === 1) {
            showReviewStep();
        }
    });

    $back.on('click', function () {
        showReviewStep();
    });

    $form.on('submit', function () {
        if (mode === 'pay' && payStep === 1) {
            showFinalStep();
            return false;
        }
        if (submitting) {
            return false;
        }
        submitting = true;
        $submit.prop('disabled', true).text('Please wait…');
        $back.prop('disabled', true);
        return true;
    });
})(jQuery);
</script>
@endpush
@endif

@if(! empty($showPayoutSync))
@push('scripts')
<script>
(function ($) {
    var $modal = $('#wdSyncModal');
    var $form = $('#wdSyncForm');
    var $submit = $('#wdSyncSubmit');
    var submitting = false;

    $('.js-wd-sync').on('click', function () {
        $submit.prop('disabled', false).text('Yes, sync status');
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
