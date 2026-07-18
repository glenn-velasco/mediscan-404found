<?php

use App\Exceptions\ProfessionalApplicationAlreadyPendingException;
use App\Exceptions\ProfessionalApplicationAlreadyReviewedException;
use App\Exceptions\ProfessionalApplicationUploadFailedException;
use App\Exceptions\TooManyAttemptsException;
use App\Exceptions\UserInvitationLinkInvalidException;
use App\Http\Controllers\Api\Controller as ApiController;
use App\Http\Middleware\EnsureApiUserActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogHttpRequests;
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
        // Trust all direct connections as proxies. `app` (and
        // horizon/reverb/scheduler) are never on the `public` Docker
        // network (infrastructure/docker-compose.staging.yml /
        // .production.yml) - nginx is the only thing that can ever
        // connect to them directly, so "trust whoever connects directly"
        // is equivalent to "trust nginx", without needing to know nginx's
        // exact (and Docker-assigned, not fixed) IP. This also means
        // nginx's forwarded CF-Connecting-IP-derived X-Forwarded-For
        // (infrastructure/docker/nginx/nginx.conf's real_ip_header) is
        // honored, so $request->ip() reflects the real client.
        //
        // Without trusting this hop, X-Forwarded-Proto from nginx is
        // ignored, Laravel thinks every request is plain HTTP, and any
        // redirect it issues uses an http:// Location - which browsers
        // block as mixed content when followed by an Inertia `<Link>`
        // (fetch), even though a full page load tolerates it via nginx's
        // own http->https bounce.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            LogHttpRequests::class,
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

        $exceptions->renderable(fn (ProfessionalApplicationAlreadyPendingException $exception) => redirect()
            ->back()
            ->withErrors(['id_type' => $exception->getMessage()])
        );

        $exceptions->renderable(fn (ProfessionalApplicationUploadFailedException $exception) => redirect()
            ->back()
            ->withErrors(['id_photo' => $exception->getMessage()])
        );

        $exceptions->renderable(function (ProfessionalApplicationAlreadyReviewedException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('admin.professional-applications.index');
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
                $e instanceof ProfessionalApplicationAlreadyPendingException => ApiController::error($e->getMessage(), 422),
                $e instanceof ProfessionalApplicationUploadFailedException => ApiController::error($e->getMessage(), 422),
                $e instanceof AuthenticationException => ApiController::error('Unauthenticated.', 401),
                $e instanceof MethodNotAllowedHttpException => ApiController::error('Method not allowed.', 405),
                $e instanceof NotFoundHttpException => ApiController::error('Not found.', 404),
                default => null,
            };
        });

    })->create();
