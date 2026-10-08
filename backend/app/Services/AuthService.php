<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => UserRole::Customer,
        ]);

        return ['user' => $user, 'token' => $this->createToken($user)];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new ApiException('These credentials do not match our records.', 'invalid_credentials', 401);
        }

        return ['user' => $user, 'token' => $this->createToken($user)];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function createToken(User $user): string
    {
        $abilities = $user->isAdmin() ? ['*'] : ['customer'];

        return $user->createToken('menumind-web', $abilities)->plainTextToken;
    }
}
