<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function test_user_register_creates_active_reporter_and_prints_token(): void
    {
        $exit = Artisan::call('user:register', ['email' => 'reporter@example.com']);

        $this->assertSame(0, $exit);

        $token = trim(Artisan::output());
        $this->assertMatchesRegularExpression('/^\d+\|.+/', $token);

        $user = User::query()->where('email', 'reporter@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_active);
        $this->assertSame('reporter', $user->role);

        [$id, $plain] = explode('|', $token, 2);
        $accessToken = $user->tokens()->first();

        $this->assertNotNull($accessToken);
        $this->assertSame($id, (string) $accessToken->getKey());
        $this->assertSame(hash('sha256', $plain), $accessToken->token);
        $this->assertSame('cli', $accessToken->name);
    }

    public function test_user_register_rejects_duplicate_email(): void
    {
        $this->assertSame(0, Artisan::call('user:register', ['email' => 'reporter@example.com']));
        $this->assertSame(1, Artisan::call('user:register', ['email' => 'reporter@example.com']));
        $this->assertSame(1, User::query()->where('email', 'reporter@example.com')->count());
    }

    public function test_public_register_and_login_routes_are_absent(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'reporter@example.com'])->assertNotFound();
        $this->postJson('/api/auth/login', ['email' => 'reporter@example.com'])->assertNotFound();
    }
}
