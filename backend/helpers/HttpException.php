<?php
/**
 * Throw this anywhere in the backend to stop and send an error response.
 *
 * Example: throw new HttpException(404, 'Az állat nem található.');
 */
class HttpException extends Exception
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}
