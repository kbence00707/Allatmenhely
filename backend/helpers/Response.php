<?php
/**
 * Sends JSON responses with the correct HTTP status code.
 */
class Response
{
    public static function json(array $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // JSON_UNESCAPED_UNICODE keeps Hungarian letters (á, ő, ű) readable.
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(int $status, string $message, array $details = []): void
    {
        $body = ['error' => $message];
        if ($details) {
            $body['details'] = $details;
        }
        self::json($body, $status);
    }
}
