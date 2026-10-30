@extends('layouts.admin')
@section('title', 'Edit User #'.$user->id)
@section('content')
<p class="m-b-md">
    <a href="{{ route('admin.users.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back to Active Users</a>
</p>

<div class="row">
    <div class="col-lg-7">
        <div class="ibox">
            <div class="ibox-title"><h5>Profile details</h5></div>
            <div class="ibox-content">
                <p class="text-muted">Name, email, and phone can be changed. Email must stay unique, same as registration. Phone may be repeated.</p>
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label for="name">Full name</label>
                        <input id="name" class="form-control" name="name" value="{{ old('name', $user->name) }}" maxlength="60" required autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="100" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" class="form-control" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" autocomplete="tel">
                    </div>
                    <button type="submit" class="btn btn-primary">Save profile</button>
                </form>
            </div>
        </div>

        <div class="ibox">
            <div class="ibox-title"><h5>Change password</h5></div>
            <div class="ibox-content">
                <p class="text-muted">Set a new login code for this customer. Their current password is not required. Use at least 8 characters with mixed case, a number, and a symbol.</p>
                <form method="POST" action="{{ route('admin.users.password', $user) }}" autocomplete="off">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label for="password">New password</label>
                        <input id="password" class="form-control" type="password" name="password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Update password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ibox">
            <div class="ibox-title"><h5>Account (read only)</h5></div>
            <div class="ibox-content">
                <dl class="m-b-none cmc-readonly-dl">
                    <dt>Customer ID</dt>
                    <dd>{{ $user->id }}</dd>
                    <dt>Country</dt>
                    <dd>{{ $user->country ?: '—' }}</dd>
                    <dt>Sponsor ID</dt>
                    <dd>{{ $user->sponsor_id ? '#'.$user->sponsor_id.($user->sponsor?->name ? ' '.$user->sponsor->name : '') : '—' }}</dd>
                    <dt>Placement ID</dt>
                    <dd>{{ $user->parent_id ? '#'.$user->parent_id.($user->parent?->name ? ' '.$user->parent->name : '') : '—' }}</dd>
                    <dt>Position</dt>
                    <dd>{{ $user->position?->value ?? '—' }}</dd>
                    <dt>Package</dt>
                    <dd>{{ $user->package->name ?? '—' }}</dd>
                    <dt>Wallet</dt>
                    <dd>${{ number_format((float) $user->wallet_balance, 2) }}</dd>
                    <dt>Expiry</dt>
                    <dd>{{ \App\Support\IncomeCalendar::formatDate($user->expiry_date) }}</dd>
                    <dt>Status</dt>
                    <dd>{{ $user->is_active ? 'Active' : 'Inactive' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
