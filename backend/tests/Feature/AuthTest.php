<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sami Ben Salah',
            'email' => 'Sami@Example.tn',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'sami@example.tn')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['email' => 'sami@example.tn', 'role' => 'customer']);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.tn']);

        $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'taken@example.tn',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['email']]]);
    }

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'demo@menumind.tn']);

        $this->postJson('/api/auth/login', ['email' => 'demo@menumind.tn', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'demo@menumind.tn')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'demo@menumind.tn']);

        $this->postJson('/api/auth/login', ['email' => 'demo@menumind.tn', 'password' => 'nope'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['error' => [
                'status' => 401,
                'code' => 'unauthenticated',
                'message' => 'Authentication is required.',
            ]]);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
