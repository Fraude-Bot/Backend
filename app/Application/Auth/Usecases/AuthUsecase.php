<?php

namespace App\Application\Auth\Usecases;

use App\Application\Auth\Commands\LoginCommand;
use App\Application\Auth\Commands\LogoutCommand;
use App\Application\Auth\Commands\RegisterCommand;
use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthUsecase implements AuthUsecaseInterface
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function register(RegisterCommand $command): array
    {
        $user = $this->users->createReporter($command->username, $command->email, $command->password);

        return $this->tokenResponse($user, 'registration');
    }

    public function login(LoginCommand $command): array
    {
        $user = $this->users->findByEmail($command->email);

        if (! $user || ! $user->is_active || ! Hash::check($command->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        return $this->tokenResponse($user, $command->deviceName);
    }

    public function logout(LogoutCommand $command): void
    {
        $this->users->deleteCurrentToken($command->user);
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
