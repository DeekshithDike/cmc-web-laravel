@extends('layouts.customer')
@section('title', 'My Tree')
@section('heading', 'My Tree')
@section('content')
<div class="bg-surface border border-border rounded-2xl p-6 text-center shadow-sm">
    <div class="inline-flex flex-col items-center gap-1 rounded-2xl bg-primary text-white px-6 py-4 mb-6">
        <span class="text-xs opacity-80">You</span>
        <strong class="text-lg">#{{ $user->id }} {{ $user->name }}</strong>
        <span class="text-sm opacity-90">{{ $user->package->name ?? 'No package' }}</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-3xl mx-auto text-left">
        <div class="bg-subtle border border-border rounded-2xl p-4">
            <h3 class="font-semibold text-heading mb-2">Left</h3>
            @if($left)
                <p class="text-sm text-text mb-2"><strong>#{{ $left->id }}</strong> {{ $left->name }}</p>
                <a class="text-sm font-medium text-primary" href="{{ route('customer.tree.show', $left->id) }}">Open tree →</a>
            @else
                <p class="text-sm text-muted mb-2">Empty seat</p>
                <input type="text" readonly value="{{ $leftLink }}" class="w-full text-xs h-10 px-3 rounded-xl bg-surface border border-border" onclick="this.select()">
            @endif
        </div>
        <div class="bg-subtle border border-border rounded-2xl p-4">
            <h3 class="font-semibold text-heading mb-2">Right</h3>
            @if($right)
                <p class="text-sm text-text mb-2"><strong>#{{ $right->id }}</strong> {{ $right->name }}</p>
                <a class="text-sm font-medium text-primary" href="{{ route('customer.tree.show', $right->id) }}">Open tree →</a>
            @else
                <p class="text-sm text-muted mb-2">Empty seat</p>
                <input type="text" readonly value="{{ $rightLink }}" class="w-full text-xs h-10 px-3 rounded-xl bg-surface border border-border" onclick="this.select()">
            @endif
        </div>
    </div>
</div>
@endsection
