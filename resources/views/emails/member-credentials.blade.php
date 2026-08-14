@component('mail::message')
# Your {{ config('citymax.name') }} account is active

Save these login details. They are also shown once on the credentials page after payment.

**Customer ID:** {{ $loginId }}

**Password:** {{ $password }}

@component('mail::button', ['url' => route('customer.login')])
Customer Login
@endcomponent

Thanks,<br>
{{ config('citymax.name') }}
@endcomponent
