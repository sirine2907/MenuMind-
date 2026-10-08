<?php

namespace App\Exceptions;

use Exception;

// Thrown for business errors, e.g. ordering a dish that is not available.
class ApiException extends Exception
{
    public string $errorCode;

    public int $status;

    public array $details;

    public function __construct(string $message, string $errorCode = 'bad_request', int $status = 400, array $details = [])
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
    }
}
