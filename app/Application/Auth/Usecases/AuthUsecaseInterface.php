<?php

namespace App\Application\Auth\Usecases;

use App\Application\Auth\Commands\LoginCommand;
use App\Application\Auth\Commands\LogoutCommand;
use App\Application\Auth\Commands\RegisterCommand;

interface AuthUsecaseInterface
{
    /**
     * @return array{token: string, expires_at: string|null, user: array<string, mixed>}
     */
    public function register(RegisterCommand $command): array;

    /**
     * @return array{token: string, expires_at: string|null, user: array<string, mixed>}
     */
    public function login(LoginCommand $command): array;

    public function logout(LogoutCommand $command): void;
}
