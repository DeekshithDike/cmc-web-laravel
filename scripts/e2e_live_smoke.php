<?php

/**
 * Live HTTP smoke checks against running CMC services.
 * Usage: php scripts/e2e_live_smoke.php
 */

$web = getenv('CMC_WEB_URL') ?: 'http://127.0.0.1:8080';
$calc = getenv('CMC_CALC_URL') ?: 'http://127.0.0.1:3001';
$apiKey = envValue('CMC_CALC_KEY') ?: envValue('CALC_INTERNAL_API_KEY');

$results = [];
$failed = 0;

function envValue(string $key): string
{
    $direct = getenv($key);
    if (is_string($direct) && $direct !== '') {
        return $direct;
    }

    $path = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';
    if (! is_file($path)) {
        return '';
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! str_starts_with($line, $key.'=')) {
            continue;
        }

        return trim(substr($line, strlen($key) + 1), " \t\"'");
    }

    return '';
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $results, $failed;
    $results[] = [
        'name' => $name,
        'ok' => $ok,
        'detail' => $detail,
    ];
    if (! $ok) {
        $failed++;
    }
    echo ($ok ? 'PASS' : 'FAIL')."  {$name}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
}

function http(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if (isset($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);

        return ['status' => 0, 'headers' => '', 'body' => $err];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

echo "=== City Max Crypto live e2e smoke ===".PHP_EOL;
echo "Web:  {$web}".PHP_EOL;
echo "Calc: {$calc}".PHP_EOL.PHP_EOL;

$health = http('GET', rtrim($calc, '/').'/health');
$healthJson = json_decode($health['body'], true) ?: [];
check(
    'Calc /health',
    $health['status'] === 200 && ($healthJson['ok'] ?? false) === true && ($healthJson['service'] ?? '') === 'cmc-node-bgt',
    'status='.$health['status'].' body='.substr($health['body'], 0, 120)
);

$unauth = http('POST', rtrim($calc, '/').'/internal/jobs/place-member', [
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode(['userId' => 1]),
]);
check('Calc rejects missing API key', $unauth['status'] === 401, 'status='.$unauth['status']);

$auth = http('POST', rtrim($calc, '/').'/internal/jobs/place-member?sync=1', [
    'headers' => [
        'Content-Type: application/json',
        'X-Internal-Api-Key: '.$apiKey,
    ],
    'body' => json_encode(['userId' => 2, 'position' => 'left', 'parentId' => 2, 'sync' => true]),
]);
$authJson = json_decode($auth['body'], true) ?: [];
check(
    'Calc place-member with API key',
    in_array($auth['status'], [200, 202], true) && ($authJson['ok'] ?? false) === true,
    'status='.$auth['status'].' body='.substr($auth['body'], 0, 160)
);

$landing = http('GET', rtrim($web, '/').'/');
check('Web landing 200', $landing['status'] === 200, 'status='.$landing['status']);
check('Web landing brand', str_contains($landing['body'], 'City Max Crypto'), 'brand present');
check('Web security X-Frame-Options', str_contains(strtolower($landing['headers']), 'x-frame-options: sameorigin'));
check('Web security CSP', str_contains(strtolower($landing['headers']), 'content-security-policy:'));
check('Web no template leak (hrivo)', ! str_contains(strtolower($landing['body']), 'hrivo'));
check('Web no coinbase leak', ! str_contains(strtolower($landing['body']), 'coinbase'));

$adminLogin = http('GET', rtrim($web, '/').'/admin/login');
check('Admin login page', $adminLogin['status'] === 200 && str_contains($adminLogin['body'], 'City Max Crypto'));

$customerLogin = http('GET', rtrim($web, '/').'/customer/login');
check('Customer login page', $customerLogin['status'] === 200 && str_contains($customerLogin['body'], 'City Max Crypto'));

$up = http('GET', rtrim($web, '/').'/up');
check('Laravel /up health', $up['status'] === 200, 'status='.$up['status']);

echo PHP_EOL.'=== Summary ==='.PHP_EOL;
$total = count($results);
$passed = $total - $failed;
echo "Passed: {$passed}/{$total}".PHP_EOL;
echo "Failed: {$failed}/{$total}".PHP_EOL;

exit($failed > 0 ? 1 : 0);
