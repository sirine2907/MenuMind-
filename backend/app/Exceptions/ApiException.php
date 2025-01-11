<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain-level error that should reach the client with a stable, machine
 * readable code (e.g. "dish_unavailable") instead of a generic 500.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'bad_request',
        public readonly int $status = 400,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
