@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Start Manual Payment</h5></div>
    <div class="ibox-content">
        <form method="POST" action="{{ route('admin.payments.start') }}" class="form-inline m-b-md">
            @csrf
            <input class="form-control m-r-sm" type="number" name="user_id" placeholder="Customer ID" required>
            <input class="form-control m-r-sm" type="number" step="0.01" name="amount" placeholder="Amount" required>
            <button class="btn btn-primary">Create pending payment</button>
        </form>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Payment Transactions</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search Payment ID / Customer ID / ref">
            <select name="status" class="form-control m-l-sm">
                <option value="">All statuses</option>
                @foreach(['pending', 'completed', 'failed'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="{{ route('admin.payments.index') }}" class="btn btn-default m-l-sm">Reset</a>
        </form>
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Customer</th><th>Provider</th><th>Ref</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($transactions as $tx)
                <tr>
                    <td>{{ $tx->id }}</td>
                    <td>{{ $tx->user_id ? '#'.$tx->user_id.' '.($tx->user->name ?? '') : ($tx->meta['signup']['email'] ?? 'pending') }}</td>
                    <td>{{ $tx->provider?->value ?? $tx->provider }}</td>
                    <td>{{ $tx->provider_ref }}</td>
                    <td>${{ number_format((float)$tx->amount, 2) }}</td>
                    <td>
                        {{ $tx->status }}
                        @if (! empty($tx->meta['activation_error']))
                            <div class="text-danger small">{{ $tx->meta['activation_error'] }}</div>
                        @endif
                    </td>
                    <td>
                        @if($tx->status === 'pending')
                            @php
                                $customerLabel = $tx->user_id
                                    ? '#'.$tx->user_id.' '.($tx->user->name ?? '')
                                    : ($tx->meta['signup']['email'] ?? ($tx->meta['power_activation']['email'] ?? 'pending'));
                                $confirmKind = ! empty($tx->meta['power_activation'])
                                    ? 'power'
                                    : (! empty($tx->meta['signup']) ? 'signup' : 'member');
                            @endphp
                            <button
                                type="button"
                                class="btn btn-primary btn-sm js-pay-confirm"
                                data-action="{{ route('admin.payments.confirm', $tx) }}"
                                data-kind="{{ $confirmKind }}"
                                data-id="{{ $tx->id }}"
                                data-customer="{{ $customerLabel }}"
                                data-provider="{{ $tx->provider?->value ?? $tx->provider }}"
                                data-ref="{{ $tx->provider_ref }}"
                                data-amount="${{ number_format((float) $tx->amount, 2) }}"
                                data-package="{{ $tx->package->name ?? '—' }}"
                            >Confirm</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        @include('admin.partials.pagination', ['paginator' => $transactions])
    </div>
</div>

<div class="modal fade" id="payConfirmModal" tabindex="-1" role="dialog" aria-labelledby="payConfirmTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="payConfirmForm">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="payConfirmTitle">Confirm this payment?</h4>
                </div>
                <div class="modal-body">
                    <p id="payConfirmLead" class="m-b-md"></p>
                    <table class="table table-bordered table-condensed m-b-md">
                        <tbody>
                        <tr><th style="width:35%">Payment ID</th><td id="payDetailId"></td></tr>
                        <tr><th>Customer</th><td id="payDetailCustomer"></td></tr>
                        <tr><th>Package</th><td id="payDetailPackage"></td></tr>
                        <tr><th>Amount</th><td id="payDetailAmount"></td></tr>
                        <tr><th>Provider</th><td id="payDetailProvider"></td></tr>
                        <tr><th>Reference</th><td id="payDetailRef"></td></tr>
                        </tbody>
                    </table>
                    <p id="payConfirmAsk" class="m-b-md font-bold">This cannot be undone from this screen. Continue only if the money has actually been received.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="payConfirmSubmit">Yes, mark as paid</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function ($) {
    var $modal = $('#payConfirmModal');
    var $form = $('#payConfirmForm');
    var $submit = $('#payConfirmSubmit');
    var submitting = false;
    var leads = {
        member: 'You are about to <strong>mark this payment as paid</strong>. If this customer is still unpaid, their account will be activated and this amount will count toward tree volume and referral.',
        signup: 'You are about to <strong>mark this payment as paid</strong> and <strong>create the new member</strong>. Login details will be emailed, and this join will count toward tree volume and referral.',
        power: 'You are about to <strong>mark this payment as paid</strong> and <strong>activate this Power ID</strong> as a live member. Login details will be emailed.'
    };

    $('.js-pay-confirm').on('click', function () {
        var $btn = $(this);
        var kind = $btn.data('kind');

        $form.attr('action', $btn.data('action'));
        $('#payConfirmLead').html(leads[kind] || leads.member);
        $('#payDetailId').text($btn.data('id'));
        $('#payDetailCustomer').text($btn.data('customer'));
        $('#payDetailPackage').text($btn.data('package'));
        $('#payDetailAmount').text($btn.data('amount'));
        $('#payDetailProvider').text($btn.data('provider'));
        $('#payDetailRef').text($btn.data('ref') || '—');

        $submit.prop('disabled', false).text('Yes, mark as paid');
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
