<?php

namespace App\Http\Controllers;

use App\Application\Auth\Commands\LoginCommand;
use App\Application\Auth\Commands\LogoutCommand;
use App\Application\Auth\Commands\RegisterCommand;
use App\Application\Auth\Usecases\AuthUsecaseInterface;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthUsecaseInterface $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return response()->json($this->auth->register(new RegisterCommand(
            username: (string) $request->validated('username'),
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
        )), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->auth->login(new LoginCommand(
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
            deviceName: (string) $request->validated('device_name', 'api-client'),
        )));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->auth->logout(new LogoutCommand($user));
        }

        return response()->json(null, 204);
    }
}
