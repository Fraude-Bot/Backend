<?php

namespace App\Application\Auth\Commands;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}
}
