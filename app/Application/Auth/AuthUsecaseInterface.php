<?php

namespace App\Application\Auth;

use App\Models\User;

interface AuthUsecaseInterface
{
    /**
     * @param  array{username: string, email: string, password: string}  $input
     * @return array{token: string, expires_at: string|null, user: array<string, mixed>}
     */
    public function register(array $input): array;

    /**
     * @return array{token: string, expires_at: string|null, user: array<string, mixed>}
     */
    public function login(string $email, string $password, string $deviceName): array;

    public function logout(User $user): void;
}
