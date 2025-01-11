<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_requires_authentication(): void
    {
        $this->postJson('/api/chat', ['message' => 'Hi'])->assertUnauthorized();
    }

    public function test_it_relays_the_message_with_user_context_to_the_agent(): void
    {
        $user = User::factory()->create(['name' => 'Yasmine']);
        $token = $user->createToken('web')->plainTextToken;

        Http::fake([
            'agent.test/chat' => Http::response([
                'answer' => 'Our Ojja Légumes is vegetarian and peanut-free.',
                'steps' => [['tool' => 'search_menu', 'label' => 'Searching menu']],
                'citations' => [['type' => 'dish', 'id' => 6, 'title' => 'Ojja Légumes']],
            ]),
        ]);

        $this->withToken($token)
            ->postJson('/api/chat', ['message' => 'Vegetarian ideas?', 'session_id' => 'abc-123', 'stream' => false])
            ->assertOk()
            ->assertJsonPath('data.session_id', 'abc-123')
            ->assertJsonPath('data.answer', 'Our Ojja Légumes is vegetarian and peanut-free.')
            ->assertJsonPath('data.citations.0.title', 'Ojja Légumes');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://agent.test/chat'
            && $request->header('X-Agent-Secret')[0] === 'test-secret'
            && $request['session_id'] === "u{$user->id}:abc-123"
            && $request['user']['name'] === 'Yasmine'
            && $request['api_token'] === $token);
    }

    public function test_it_streams_server_sent_events_from_the_agent(): void
    {
        $user = User::factory()->create();
        $events = "event: step\ndata: {\"tool\":\"search_menu\"}\n\nevent: token\ndata: {\"text\":\"Hello\"}\n\nevent: done\ndata: {}\n\n";

        Http::fake(['agent.test/chat/stream' => Http::response($events, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->withToken($user->createToken('web')->plainTextToken)
            ->post('/api/chat', ['message' => 'Hello', 'session_id' => 's1'], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertSame('s1', $response->headers->get('X-Session-Id'));
        $this->assertSame($events, $response->streamedContent());
    }

    public function test_an_unreachable_agent_returns_a_clean_error(): void
    {
        $user = User::factory()->create();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

        $this->withToken($user->createToken('web')->plainTextToken)
            ->postJson('/api/chat', ['message' => 'Hello', 'stream' => false])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'agent_unavailable');
    }

    public function test_chat_input_is_validated(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('web')->plainTextToken)
            ->postJson('/api/chat', ['message' => '', 'session_id' => '../../etc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('message', 'error.details')
            ->assertJsonValidationErrorFor('session_id', 'error.details');
    }
}
