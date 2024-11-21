<?php

namespace App\Services\Chat;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Relays chat turns to the Python agent service.
 *
 * The agent receives the caller's own Sanctum token so every tool call it
 * makes back into this API (search, allergen check, create order...) runs
 * with the customer's permissions, never with elevated ones.
 */
class AgentClient
{
    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, string $token, string $message, string $sessionId): array
    {
        return [
            // Namespacing by user id means one customer can never resume another's session.
            'session_id' => "u{$user->id}:{$sessionId}",
            'message' => $message,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
            ],
            'api_token' => $token,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function chat(array $payload): array
    {
        $response = $this->send('/chat', $payload);

        return $response->json();
    }

    /**
     * Opens a streamed (SSE) request; the caller pipes the body to the client.
     *
     * @param  array<string, mixed>  $payload
     */
    public function stream(array $payload): Response
    {
        return $this->send('/chat/stream', $payload, stream: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload, bool $stream = false): Response
    {
        try {
            $response = $this->request($stream)->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new ApiException('The assistant is currently unavailable. Please try again in a moment.', 'agent_unavailable', 503);
        }

        if ($response->failed()) {
            throw new ApiException(
                'The assistant could not process this message.',
                'agent_error',
                502,
                ['agent_status' => $response->status()],
            );
        }

        return $response;
    }

    private function request(bool $stream): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.agent.url'), '/'))
            ->withHeaders(array_filter(['X-Agent-Secret' => config('services.agent.secret')]))
            ->accept($stream ? 'text/event-stream' : 'application/json')
            ->timeout((int) config('services.agent.timeout'))
            ->connectTimeout(5)
            ->withOptions(['stream' => $stream]);
    }
}
