@extends('layouts.customer-guest')

@section('title', 'Payment cancelled')

@section('content')
<div class="text-center">
    <span class="cmc-stat-icon mx-auto mb-3"><i class="ph ph-x-circle"></i></span>
    <span class="cmc-chip"><i class="ph ph-x-circle"></i> Cancelled</span>
</div>
<h2 class="text-xl font-bold text-heading text-center mt-3">Payment cancelled</h2>
<p class="text-sm text-muted text-center mt-2">Signup again using the link</p>
<p class="text-sm text-muted text-center mt-5">
    <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="{{ route('landing') }}">Back to home</a>
</p>
@endsection
