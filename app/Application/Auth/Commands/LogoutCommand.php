<?php

namespace App\Application\Auth\Commands;

use App\Models\User;

final readonly class LogoutCommand
{
    public function __construct(
        public User $user,
    ) {}
}
