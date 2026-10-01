<?php

namespace App\Http\Controllers;

use App\Application\Auth\AuthUsecaseInterface;
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
        return response()->json($this->auth->register([
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name', 'api-client'),
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->auth->logout($user);
        }

        return response()->json(null, 204);
    }
}
