<?php

use App\Application\Tasks\DeleteTemporaryMediaTask;
use App\Http\Middleware\EnsureResponseIsJSON;
use App\Http\Middleware\RequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: [
            __DIR__.'/../routes/api.auth.php',
            __DIR__.'/../routes/api.admin.php',
            __DIR__.'/../routes/api.public.php',
        ],
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
        $middleware->append([
            RequestContext::class,
            EnsureResponseIsJSON::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            $scalarPath = trim((string) config('scalar.path', 'scalar'), '/');

            if ($scalarPath !== '' && $request->is($scalarPath, $scalarPath.'/*')) {
                return false;
            }

            return true;
        });
        $exceptions->render(function (ValidationException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'The request data is invalid.',
                    'details' => $exception->errors(),
                ],
            ], 422);
        });
        $exceptions->render(function (AuthenticationException $exception) {
            return response()->json([
                'error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.'],
            ], 401);
        });
        $exceptions->render(function (AuthorizationException $exception) {
            return response()->json([
                'error' => ['code' => 'forbidden', 'message' => $exception->getMessage() ?: 'This action is forbidden.'],
            ], 403);
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            return response()->json([
                'error' => [
                    'code' => match ($exception->getStatusCode()) {
                        404 => 'not_found',
                        409 => 'conflict',
                        429 => 'rate_limited',
                        default => 'http_error',
                    },
                    'message' => $exception->getMessage() ?: 'The request could not be completed.',
                ],
            ], $exception->getStatusCode());
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(DeleteTemporaryMediaTask::class)->weekly();
    })
    ->create();
