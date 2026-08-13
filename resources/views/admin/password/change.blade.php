@extends('layouts.admin')
@section('title', 'Change Password')
@section('heading', 'Change Password')
@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.password.update') }}">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn">Update password</button>
    </form>
</div>
@endsection
