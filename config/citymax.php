<?php

return [
    'name' => env('APP_NAME', 'City Max Crypto'),
    'tagline' => env('APP_TAGLINE', 'CREATE • CONNECT • CONQUER'),

    'calc' => [
        'base_url' => env('CALC_SERVICE_URL', 'http://127.0.0.1:3001'),
        'internal_api_key' => env('CALC_INTERNAL_API_KEY'),
        'request_timeout' => (int) env('CALC_REQUEST_TIMEOUT', 30),
    ],

    'withdrawal' => [
        'minimum' => (float) env('WITHDRAWAL_MINIMUM', 20),
        'fee' => (float) env('WITHDRAWAL_FEE', 5),
    ],

    'membership' => [
        'weekdays' => (int) env('MEMBERSHIP_WEEKDAYS', 150),
        'expiry_warning_days' => (int) env('MEMBERSHIP_EXPIRY_WARNING_DAYS', 10),
    ],

    'income' => [
        'referral_percent' => (float) env('INCOME_REFERRAL_PERCENT', 10),
        'binary_percent' => (float) env('INCOME_BINARY_PERCENT', 5),
        'binary_max' => (float) env('INCOME_BINARY_MAX', 500),
        'run_at' => env('INCOME_RUN_AT', '00:05'),
    ],

    'seed' => [
        'admin_id' => (int) env('SEED_ADMIN_ID', 1),
        'admin_name' => env('SEED_ADMIN_NAME', 'City Max Admin'),
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@citymaxcrypto.com'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'customer_id' => (int) env('SEED_CUSTOMER_ID', 3558),
        'customer_name' => env('SEED_CUSTOMER_NAME', 'Root Customer'),
        'customer_email' => env('SEED_CUSTOMER_EMAIL', 'root@citymaxcrypto.com'),
        'customer_password' => env('SEED_CUSTOMER_PASSWORD'),
        'customer_phone' => env('SEED_CUSTOMER_PHONE', '0000000000'),
        'customer_country' => env('SEED_CUSTOMER_COUNTRY', 'US'),
        // DummyPowerIdSeeder: chain this many on left and on right under root (reference: 50+50).
        'dummy_power_ids_per_side' => (int) env('SEED_DUMMY_POWER_IDS_PER_SIDE', 100),
    ],

    'packages' => [
        ['name' => 'Starter', 'amount' => '100.00', 'roi_percent' => '1.00', 'sort_order' => 1],
        ['name' => 'Silver', 'amount' => '250.00', 'roi_percent' => '1.00', 'sort_order' => 2],
        ['name' => 'Gold', 'amount' => '500.00', 'roi_percent' => '1.00', 'sort_order' => 3],
        ['name' => 'Platinum', 'amount' => '1000.00', 'roi_percent' => '1.00', 'sort_order' => 4],
        ['name' => 'Diamond', 'amount' => '1500.00', 'roi_percent' => '1.00', 'sort_order' => 5],
        ['name' => 'Elite', 'amount' => '2000.00', 'roi_percent' => '1.00', 'sort_order' => 6],
        ['name' => 'Premier', 'amount' => '3000.00', 'roi_percent' => '1.00', 'sort_order' => 7],
        ['name' => 'Royal', 'amount' => '4000.00', 'roi_percent' => '1.00', 'sort_order' => 8],
        ['name' => 'Imperial', 'amount' => '5000.00', 'roi_percent' => '1.00', 'sort_order' => 9],
    ],
];

