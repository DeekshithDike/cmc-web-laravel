@extends('layouts.admin')
@section('title', 'Add New User')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Add New User</h5></div>
    <div class="ibox-content">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="form-group"><label>Full Name</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
            <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="{{ old('email') }}" required></div>
            <div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="{{ old('phone') }}"></div>
            <div class="form-group"><label>Country</label><input class="form-control" name="country" value="{{ old('country') }}"></div>
            <div class="form-group"><label>Sponsor ID</label><input class="form-control" type="number" name="sponsor_id" value="{{ old('sponsor_id', 2) }}" required></div>
            <div class="form-group"><label>Placement ID</label><input class="form-control" type="number" name="parent_id" value="{{ old('parent_id', 2) }}" required></div>
            <div class="form-group"><label>Position</label>
                <select class="form-control" name="position" required>
                    <option value="left" @selected(old('position')==='left')>Left</option>
                    <option value="right" @selected(old('position')==='right')>Right</option>
                </select>
            </div>
            <div class="form-group"><label>Package</label>
                <select class="form-control" name="package_id" required>
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}">{{ $package->name }} (${{ $package->amount }})</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary">Create account</button>
        </form>
    </div>
</div>
@endsection
