<?php

namespace App\Application\Auth\Commands;

final readonly class RegisterCommand
{
    public function __construct(
        public string $email,
    ) {}
}
