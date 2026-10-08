<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

// Every API error is returned in the same format:
// { "error": { "status": 422, "code": "validation_failed", "message": "...", "details": {...} } }
class ApiExceptionRenderer
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        422 => 'unprocessable_entity',
        429 => 'too_many_requests',
        502 => 'bad_gateway',
        503 => 'service_unavailable',
    ];

    public function render(Throwable $e): JsonResponse
    {
        if ($e instanceof ApiException) {
            return $this->error($e->status, $e->errorCode, $e->getMessage(), $e->details);
        }

        if ($e instanceof ValidationException) {
            return $this->error(422, 'validation_failed', $e->getMessage(), $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return $this->error(401, 'unauthenticated', 'Authentication is required.');
        }

        if ($e instanceof AuthorizationException) {
            return $this->error(403, 'forbidden', $e->getMessage() ?: 'This action is unauthorized.');
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->error(404, 'not_found', $this->notFoundMessage($e));
        }

        if ($e instanceof ThrottleRequestsException) {
            return $this->error(429, 'too_many_requests', 'Too many requests, slow down.', [], $e->getHeaders());
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();

            // route model binding wraps the "not found" exception
            if ($e->getPrevious() instanceof ModelNotFoundException) {
                $message = $this->notFoundMessage($e->getPrevious());
            }

            if ($message === '') {
                $message = $status === 404 ? 'The requested resource was not found.' : 'Request failed.';
            }

            return $this->error($status, self::CODES[$status] ?? 'http_error', $message, [], $e->getHeaders());
        }

        $message = config('app.debug') ? $e->getMessage() : 'Something went wrong on our side.';

        return $this->error(500, 'server_error', $message);
    }

    private function error(int $status, string $code, string $message, array $details = [], array $headers = []): JsonResponse
    {
        $error = ['status' => $status, 'code' => $code, 'message' => $message];

        if (count($details) > 0) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status, $headers);
    }

    private function notFoundMessage(ModelNotFoundException $e): string
    {
        return class_basename($e->getModel()).' not found.';
    }
}
