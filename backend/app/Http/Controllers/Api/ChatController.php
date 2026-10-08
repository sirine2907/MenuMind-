<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Services\Chat\AgentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private AgentClient $agent) {}

    public function __invoke(ChatRequest $request): JsonResponse|StreamedResponse
    {
        $sessionId = $request->validated('session_id') ?: (string) Str::uuid();

        $payload = $this->agent->payload(
            $request->user(),
            (string) $request->bearerToken(),
            $request->validated('message'),
            $sessionId,
        );

        if (! $request->boolean('stream', true)) {
            return response()->json(['data' => [
                'session_id' => $sessionId,
                ...$this->agent->chat($payload),
            ]]);
        }

        // we connect to the agent first, so if it is down the user gets a normal JSON error
        $upstream = $this->agent->stream($payload);
        $body = $upstream->toPsrResponse()->getBody();

        return response()->stream(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(1024);

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                if (connection_aborted()) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Session-Id' => $sessionId,
        ]);
    }
}
