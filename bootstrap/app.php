<?php

use App\Exceptions\TooManyAttemptsException;
use App\Exceptions\UserInvitationLinkInvalidException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        //  GLOBAL EXCEPTION HANDLER FOR WEB DASHBOARD
        $exceptions->renderable(fn (TooManyAttemptsException $exception) =>
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage()
            ])->back()
        );

        $exceptions->renderable(function (UserInvitationLinkInvalidException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage()
            ]);

            return redirect()->route('login');
        });
        
        // GLOBAL EXCEPTION HANDLER FOR API
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
