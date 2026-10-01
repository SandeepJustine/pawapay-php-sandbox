<?php
declare(strict_types=1);

function loadEnv(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (($value[0] ?? '') === '"' && substr($value, -1) === '"') {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) putenv("$key=$value");
    }
}

loadEnv(__DIR__ . '/.env');

function env(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function config(): array {
    return [
        'token' => env('PAWAPAY_API_TOKEN', ''),
        'base_url' => rtrim(env('PAWAPAY_BASE_URL', 'https://api.sandbox.pawapay.io'), '/'),
        'country' => env('PAWAPAY_COUNTRY', 'MWI'),
        'currency' => env('PAWAPAY_CURRENCY', 'MWK'),
        'app_name' => env('APP_NAME', 'PawaPay PHP Sandbox Tester'),
    ];
}
