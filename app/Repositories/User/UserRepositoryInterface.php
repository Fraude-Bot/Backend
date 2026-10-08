<?php

namespace App\Repositories\User;

use App\Models\User;

interface UserRepositoryInterface
{
    public function createReporter(string $email): User;

    public function findByEmail(string $email): ?User;

    public function deleteCurrentToken(User $user): void;
}
