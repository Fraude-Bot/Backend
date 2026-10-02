<?php

namespace App\Application\Auth;

use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthUsecase implements AuthUsecaseInterface
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function register(array $input): array
    {
        $user = $this->users->createReporter($input['username'], $input['email'], $input['password']);

        return $this->tokenResponse($user, 'registration');
    }

    public function login(string $email, string $password, string $deviceName): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! $user->is_active || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        return $this->tokenResponse($user, $deviceName);
    }

    public function logout(User $user): void
    {
        $this->users->deleteCurrentToken($user);
    }

    /**
     * @return array{token: string, expires_at: string|null, user: array<string, mixed>}
     */
    private function tokenResponse(User $user, string $deviceName): array
    {
        $abilities = in_array($user->role, ['admin', 'moderator'], true)
            ? ['admin:write']
            : [];

        $token = $user->createToken(
            $deviceName,
            $abilities,
            now()->addMinutes((int) config('sanctum.expiration')),
        );

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'user' => $user->only(['id', 'username', 'email', 'role']),
        ];
    }
}
