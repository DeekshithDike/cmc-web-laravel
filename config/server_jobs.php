<?php

$ips = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('SERVER_JOB_ALLOWED_IPS', ''))
)));

return [
    // Empty secret disables the jobs (every call is rejected).
    'secret' => (string) env('SERVER_JOB_SECRET', ''),
    'income_path' => trim((string) env('SERVER_JOB_INCOME_PATH', ''), '/'),
    'payment_path' => trim((string) env('SERVER_JOB_PAYMENT_PATH', ''), '/'),
    'allowed_ips' => $ips,
    'skew_seconds' => 60,
];
