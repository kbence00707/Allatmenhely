<?php
/**
 * CORS (Cross-Origin Resource Sharing).
 *
 * When the frontend is opened from http://localhost/Allatmenhely/ it is on the
 * SAME origin as the API, and nothing here is needed.
 * If the frontend runs somewhere else (e.g. VS Code Live Server on port 5500),
 * the browser asks the API for permission first. We only allow the origins
 * listed in CORS_ORIGINS (backend/.env).
 */
class Cors
{
    public static function handle(array $allowedOrigins): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Vary: Origin');
        }

        // The browser's "preflight" permission request: answer it and stop.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
