<?php

namespace App\Repositories\User;

use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function createReporter(string $username, string $email, string $password): User
    {
        return User::create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => 'reporter',
            'is_active' => true,
        ]);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function deleteCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
