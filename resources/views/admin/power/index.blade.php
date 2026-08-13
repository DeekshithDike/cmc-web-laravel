@extends('layouts.admin')
@section('title', 'Power ID')
@section('content')
<div class="ibox">
    <div class="ibox-title"><h5>Create Power ID</h5></div>
    <div class="ibox-content">
        <form method="POST" action="{{ route('admin.power.store') }}" class="form-inline m-b-md">
            @csrf
            <input class="form-control m-r-sm" type="number" name="parent_id" placeholder="Placement ID" required>
            <input class="form-control m-r-sm" type="number" name="sponsor_id" placeholder="Sponsor ID" required>
            <select class="form-control m-r-sm" name="position" required>
                <option value="left">Left</option>
                <option value="right">Right</option>
            </select>
            <button class="btn btn-primary">Reserve Power ID</button>
        </form>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Unused Power IDs</h5></div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Parent</th><th>Sponsor</th><th>Position</th><th>Guest pay link</th></tr></thead>
            <tbody>
            @forelse($powerIds as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ $item->parent_id }}</td>
                    <td>{{ $item->sponsor_id }}</td>
                    <td>{{ $item->position?->label() ?? $item->position }}</td>
                    <td class="text-break">
                        <input class="form-control input-sm" readonly value="{{ route('customer.register.special', ['target' => encrypt((string) $item->id)]) }}" onclick="this.select()">
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No Power IDs.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $powerIds->links() }}
    </div>
</div>
@endsection
