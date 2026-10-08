<?php

namespace App\Application\Auth\Usecases;

use App\Application\Auth\Commands\LogoutCommand;
use App\Application\Auth\Commands\RegisterCommand;
use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Validation\ValidationException;

class AuthUsecase implements AuthUsecaseInterface
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function register(RegisterCommand $command): array
    {
        if ($this->users->findByEmail($command->email) !== null) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        $user = $this->users->createReporter($command->email);

        return $this->tokenResponse($user);
    }

    public function logout(LogoutCommand $command): void
    {
        $this->users->deleteCurrentToken($command->user);
    }

    /**
     * @return array{token: string, expires_at: string|null, user: array<string, mixed>}
     */
    private function tokenResponse(User $user): array
    {
        $abilities = in_array($user->role, ['admin', 'moderator'], true)
            ? ['admin:write']
            : [];

        $token = $user->createToken(
            'cli',
            $abilities,
            now()->addMinutes((int) config('sanctum.expiration')),
        );

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'user' => $user->only(['id', 'email', 'role']),
        ];
    }
}
