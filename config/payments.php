<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default drivers
    |--------------------------------------------------------------------------
    |
    | Switch providers without code changes:
    |   PAYMENT_PROVIDER = manual | nowpayments
    |   PAYOUT_PROVIDER  = manual | nowpayments
    |
    */
    'default_receive' => env('PAYMENT_PROVIDER', 'manual'),
    'default_payout' => env('PAYOUT_PROVIDER', 'manual'),
    // null = stub only in local/testing. Set true/false to override.
    'allow_stub' => env('PAYMENT_ALLOW_STUB'),

    'nowpayments' => [
        'api_key' => env('NOWPAYMENTS_API_KEY'),
        'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
        'email' => env('NOWPAYMENTS_EMAIL'),
        'password' => env('NOWPAYMENTS_PASSWORD'),
        'two_fa_secret' => env('NOWPAYMENTS_2FA_SECRET'),
        'base_url' => rtrim(env('NOWPAYMENTS_API_URL', 'https://api.nowpayments.io/v1'), '/'),
        'price_currency' => strtolower(env('NOWPAYMENTS_PRICE_CURRENCY', 'usd')),
        'payout_currency' => strtolower(env('NOWPAYMENTS_PAYOUT_CURRENCY', 'usdttrc20')),
        'payout_fiat_currency' => strtolower(env('NOWPAYMENTS_PAYOUT_FIAT_CURRENCY', env('NOWPAYMENTS_PRICE_CURRENCY', 'usd'))),
        'validate_payout_address' => (bool) env('NOWPAYMENTS_VALIDATE_ADDRESS', true),
        'http_timeout' => (int) env('NOWPAYMENTS_HTTP_TIMEOUT', 30),
        'auth_timeout' => (int) env('NOWPAYMENTS_AUTH_TIMEOUT', 20),
        'jwt_cache_minutes' => (int) env('NOWPAYMENTS_JWT_CACHE_MINUTES', 4),
    ],
];
