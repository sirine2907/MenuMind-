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

/**
 * Renders every API error with the same envelope:
 *
 * { "error": { "status": 422, "code": "validation_failed", "message": "...", "details": {...} } }
 */
class ApiExceptionRenderer
{
    private const HTTP_CODES = [
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
        [$status, $code, $message, $details] = match (true) {
            $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), $e->details],
            $e instanceof ValidationException => [422, 'validation_failed', $e->getMessage(), $e->errors()],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Authentication is required.', []],
            $e instanceof AuthorizationException => [403, 'forbidden', $e->getMessage() ?: 'This action is unauthorized.', []],
            $e instanceof ModelNotFoundException => [404, 'not_found', $this->modelNotFoundMessage($e), []],
            $e instanceof ThrottleRequestsException => [429, 'too_many_requests', 'Too many requests, slow down.', []],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                self::HTTP_CODES[$e->getStatusCode()] ?? 'http_error',
                $this->httpMessage($e),
                [],
            ],
            default => [500, 'server_error', config('app.debug') ? $e->getMessage() : 'Something went wrong on our side.', []],
        };

        $body = ['status' => $status, 'code' => $code, 'message' => $message];

        if ($details !== []) {
            $body['details'] = $details;
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse(['error' => $body], $status, $headers);
    }

    private function modelNotFoundMessage(ModelNotFoundException $e): string
    {
        $model = class_basename($e->getModel());

        return "{$model} not found.";
    }

    private function httpMessage(HttpExceptionInterface&Throwable $e): string
    {
        $previous = $e->getPrevious();

        if ($previous instanceof ModelNotFoundException) {
            return $this->modelNotFoundMessage($previous);
        }

        return $e->getMessage() !== '' ? $e->getMessage() : match ($e->getStatusCode()) {
            404 => 'The requested resource was not found.',
            405 => 'Method not allowed.',
            default => 'Request failed.',
        };
    }
}
