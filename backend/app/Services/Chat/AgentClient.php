<?php

namespace App\Services\Chat;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

// Sends the chat messages to the Python agent service.
class AgentClient
{
    public function payload(User $user, string $token, string $message, string $sessionId): array
    {
        return [
            // the user id is added so a user can't read another user's conversation
            'session_id' => "u{$user->id}:{$sessionId}",
            'message' => $message,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
            ],
            // the agent calls our API with the user's own token,
            // so it can't do more than the user is allowed to
            'api_token' => $token,
        ];
    }

    public function chat(array $payload): array
    {
        return $this->send('/chat', $payload)->json();
    }

    // Returns the response without reading it, the controller streams the body
    public function stream(array $payload): Response
    {
        return $this->send('/chat/stream', $payload, true);
    }

    private function send(string $path, array $payload, bool $stream = false): Response
    {
        $request = Http::baseUrl(rtrim(config('services.agent.url'), '/'))
            ->accept($stream ? 'text/event-stream' : 'application/json')
            ->timeout((int) config('services.agent.timeout'))
            ->connectTimeout(5)
            ->withOptions(['stream' => $stream]);

        if (config('services.agent.secret')) {
            $request->withHeaders(['X-Agent-Secret' => config('services.agent.secret')]);
        }

        try {
            $response = $request->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new ApiException('The assistant is currently unavailable. Please try again in a moment.', 'agent_unavailable', 503);
        }

        if ($response->failed()) {
            throw new ApiException('The assistant could not process this message.', 'agent_error', 502, ['agent_status' => $response->status()]);
        }

        return $response;
    }
}
