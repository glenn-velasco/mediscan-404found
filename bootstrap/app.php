<?php

use App\Exceptions\TooManyAttemptsException;
use App\Exceptions\UserInvitationLinkInvalidException;
use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Middleware\EnsureApiUserActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RestrictScribeDocsAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'api.active' => EnsureApiUserActive::class,
            'scribe.docs-access' => RestrictScribeDocsAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        //  GLOBAL EXCEPTION HANDLER FOR WEB DASHBOARD
        $exceptions->renderable(fn (TooManyAttemptsException $exception) => Inertia::flash('toast', [
            'type' => 'error',
            'message' => $exception->getMessage(),
        ])->back()
        );

        $exceptions->renderable(function (UserInvitationLinkInvalidException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('login');
        });

        // GLOBAL EXCEPTION HANDLER FOR API
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiController::error(
                    collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                    422,
                    $e->errors(),
                ),
                $e instanceof AuthenticationException => ApiController::error('Unauthenticated.', 401),
                $e instanceof MethodNotAllowedHttpException => ApiController::error('Method not allowed.', 405),
                $e instanceof NotFoundHttpException => ApiController::error('Not found.', 404),
                default => null,
            };
        });

    })->create();
