<?php
/**
 * Reads data sent by the frontend.
 */
class Request
{
    /**
     * Returns the JSON body of the request as a PHP array.
     *
     * Requiring the "application/json" content type also protects against
     * CSRF attacks: a malicious website cannot send JSON to our API with a
     * plain HTML form.
     */
    public static function json(): array
    {
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (!str_contains($contentType, 'application/json')) {
            throw new HttpException(415, 'A kérés törzsének JSON formátumúnak kell lennie (Content-Type: application/json).');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            throw new HttpException(400, 'A kérés törzse nem érvényes JSON.');
        }
        return $data;
    }

    /** Returns a trimmed query string parameter (?name=value), or null if it is missing/empty. */
    public static function query(string $name): ?string
    {
        $value = $_GET[$name] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return trim($value);
    }
}
