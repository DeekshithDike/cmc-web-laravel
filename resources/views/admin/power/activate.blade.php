@extends('layouts.admin')
@section('title', 'Activate Power ID')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Activate Power ID</h5></div>
    <div class="ibox-content">
        <form method="POST" action="{{ route('admin.power.activate.save') }}">
            @csrf
            <div class="form-group"><label>Power ID</label><input class="form-control" type="number" name="power_id" value="{{ old('power_id') }}" required></div>
            <div class="form-group"><label>Full Name</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
            <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="{{ old('email') }}" required></div>
            <div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="{{ old('phone') }}"></div>
            <div class="form-group"><label>Country</label><input class="form-control" name="country" value="{{ old('country') }}"></div>
            <div class="form-group"><label>Package</label>
                <select class="form-control" name="package_id" required>
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}">{{ $package->name }} (${{ $package->amount }})</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary">Activate</button>
        </form>
    </div>
</div>
@endsection
