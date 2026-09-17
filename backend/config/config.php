<?php
/**
 * Application configuration.
 *
 * Values are read from the backend/.env file (which is not committed to GitHub).
 * If a value is missing, the XAMPP-friendly default below is used.
 */

function loadEnvFile(string $path): array
{
    $values = [];
    if (!is_file($path)) {
        return $values;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        // Skip comments and lines without "="
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), "\"'");
    }
    return $values;
}

$env = loadEnvFile(__DIR__ . '/../.env');

$get = fn(string $key, string $default) => $env[$key] ?? $default;

return [
    'db' => [
        'host'     => $get('DB_HOST', '127.0.0.1'),
        'port'     => $get('DB_PORT', '3306'),
        'name'     => $get('DB_NAME', 'allatmenhely'),
        'user'     => $get('DB_USER', 'root'),
        'password' => $get('DB_PASS', ''),
    ],
    'cors_origins' => array_filter(array_map('trim', explode(',', $get('CORS_ORIGINS', 'http://localhost:5500,http://127.0.0.1:5500')))),
    'opening_time' => $get('OPENING_TIME', '09:00'),
    'closing_time' => $get('CLOSING_TIME', '17:00'),
    'timezone'     => $get('TIMEZONE', 'Europe/Budapest'),
    'debug'        => strtolower($get('APP_DEBUG', 'false')) === 'true',
];
