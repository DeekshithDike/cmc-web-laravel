@extends('layouts.customer-guest')

@section('title', 'Payment cancelled')

@section('content')
<h2 class="text-xl font-bold text-heading text-center">Payment cancelled</h2>
<p class="text-sm text-muted text-center mt-2">Signup again using the link</p>
<p class="text-sm text-muted text-center mt-5">
    <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="{{ route('landing') }}">Back to home</a>
</p>
@endsection
