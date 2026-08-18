@extends('layouts.admin')
@section('title', 'Withdrawals')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Withdrawals — {{ $status->label() }}</h5>
        @if($status->value === 'completed')
            <a href="{{ route('admin.withdrawals.export.completed', array_filter(['q' => $q ?? ''])) }}" class="btn btn-success btn-sm pull-right">Download Excel (CSV)</a>
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
                <th>ID</th><th>Customer</th><th>Amount</th><th>Fee</th><th>Payable</th><th>Wallet</th><th>Payout</th><th>Date</th>
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
                    <td>{{ $item->created_at?->format('Y-m-d H:i') }}</td>
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
                            data-date="{{ $item->created_at?->format('Y-m-d H:i') }}"
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
                            data-date="{{ $item->created_at?->format('Y-m-d H:i') }}"
                        >Decline</button>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $status->value === 'pending' ? 9 : 8 }}">No records.</td></tr>
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
                    <p id="wdConfirmAsk" class="m-b-md font-bold"></p>
                    <div class="form-group m-b-none">
                        <label for="wdRemarks">Remarks <span class="text-muted">(optional)</span></label>
                        <input type="text" class="form-control" name="remarks" id="wdRemarks" maxlength="255" placeholder="Optional note for this action">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="wdConfirmSubmit">Yes</button>
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
    var submitting = false;

    $('.js-wd-confirm').on('click', function () {
        var $btn = $(this);
        var mode = $btn.data('mode');
        var isPay = mode === 'pay';

        $form.attr('action', $btn.data('action'));
        $('#wdRemarks').val('');

        $('#wdConfirmTitle').text(isPay ? 'Pay this withdrawal?' : 'Decline this withdrawal?');
        $('#wdConfirmLead').html(
            isPay
                ? 'You are about to <strong>pay</strong> this withdrawal. The payable amount will be sent to the wallet address shown below.'
                : 'You are about to <strong>decline</strong> this withdrawal. The requested amount minus the fee will be refunded to the member wallet balance.'
        );
        $('#wdConfirmAsk').text(
            isPay
                ? 'This cannot be undone from this screen. Continue only if you intend to send this payout now.'
                : 'This cannot be undone from this screen. Continue only if you intend to reject this request now.'
        );

        $('#wdDetailId').text($btn.data('id'));
        $('#wdDetailUser').text($btn.data('user'));
        $('#wdDetailAmount').text($btn.data('amount'));
        $('#wdDetailFee').text($btn.data('fee'));
        $('#wdDetailPayable').text($btn.data('payable'));
        $('#wdDetailWallet').text($btn.data('wallet'));
        $('#wdDetailNetwork').text($btn.data('network') || '—');
        $('#wdDetailDate').text($btn.data('date'));

        $submit
            .removeClass('btn-primary btn-danger')
            .addClass(isPay ? 'btn-primary' : 'btn-danger')
            .prop('disabled', false)
            .text(isPay ? 'Yes, pay now' : 'Yes, decline');

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
