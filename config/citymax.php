<?php

return [
    'name' => env('APP_NAME', 'City Max Crypto'),
    'tagline' => env('APP_TAGLINE', 'CREATE • CONNECT • CONQUER'),
    'support_email' => env('SUPPORT_EMAIL', 'support@citymaxcrypto.com'),

    'seo' => [
        'html_lang' => 'en-MY',
        'locale' => 'en_MY',
        'country' => 'Malaysia',
        'country_code' => 'MY',
        'title' => env('SEO_TITLE', 'City Max Crypto | Daily ROI & USDT Packages in Malaysia'),
        'description' => env('SEO_DESCRIPTION', 'City Max Crypto is a Malaysia crypto platform with USDT packages, 1% daily ROI, referral and binary income, and fast USDT withdrawals on TRC20 and BEP20.'),
        'keywords' => env('SEO_KEYWORDS', 'City Max Crypto, CityMax, CityMax Crypto, City Max, citymax, citymax crypto Malaysia, crypto Malaysia, USDT Malaysia, Kuala Lumpur crypto, daily ROI, binary income, referral income, USDT TRC20, USDT BEP20'),
    ],

    'calc' => [
        'base_url' => env('CALC_SERVICE_URL', 'http://127.0.0.1:3001'),
        'internal_api_key' => env('CALC_INTERNAL_API_KEY'),
        'request_timeout' => (int) env('CALC_REQUEST_TIMEOUT', 30),
    ],

    'withdrawal' => [
        // Values come only from .env (WITHDRAWAL_MINIMUM, WITHDRAWAL_FEE).
        'minimum' => (float) env('WITHDRAWAL_MINIMUM'),
        'fee' => (float) env('WITHDRAWAL_FEE'),
    ],

    'membership' => [
        // Package validity length — from .env only (MEMBERSHIP_WEEKDAYS). Not a packages-table column.
        'weekdays' => (int) env('MEMBERSHIP_WEEKDAYS'),
        'expiry_warning_days' => (int) env('MEMBERSHIP_EXPIRY_WARNING_DAYS'),
    ],

    'income' => [
        // Percents/caps come only from .env. ROI uses packages.roi_percent in the database.
        'referral_percent' => (float) env('INCOME_REFERRAL_PERCENT'),
        'binary_percent' => (float) env('INCOME_BINARY_PERCENT'),
        'binary_max' => (float) env('INCOME_BINARY_MAX'),
        // Malaysia midnight — pays the calendar day that just ended.
        'run_at' => env('INCOME_RUN_AT', '00:00'),
        'timezone' => env('INCOME_TIMEZONE', 'Asia/Kuala_Lumpur'),
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

