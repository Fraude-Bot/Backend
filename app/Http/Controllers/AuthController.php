<?php

namespace App\Http\Controllers;

use App\Application\Auth\Commands\LogoutCommand;
use App\Application\Auth\Usecases\AuthUsecaseInterface;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthUsecaseInterface $auth) {}

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->auth->logout(new LogoutCommand($user));
        }

        return response()->json(null, 204);
    }
}
