<?php

use App\Exceptions\ProfessionalApplicationAlreadyPendingException;
use App\Exceptions\ProfessionalApplicationAlreadyReviewedException;
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
        // Pairs with nginx's real_ip_header CF-Connecting-IP
        // (infrastructure/docker/nginx/nginx.conf) - trusts Cloudflare's
        // published edge ranges so $request->ip() reflects the real client.
        //
        // Also trusts the `internal` Docker network's pinned subnets
        // (infrastructure/docker-compose.staging.yml / .production.yml) -
        // nginx (not Cloudflare) is the proxy Laravel actually sees, since
        // `app`/horizon/reverb/scheduler only ever sit on that network.
        // Without this, X-Forwarded-Proto from nginx is ignored, Laravel
        // thinks every request is plain HTTP, and any redirect it issues
        // uses an http:// Location - which browsers block as mixed content
        // when followed by an Inertia `<Link>` (fetch), even though a full
        // page load tolerates it via nginx's own http->https bounce.
        $middleware->trustProxies(at: [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            '10.89.0.0/24', '10.89.1.0/24',
        ]);

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

        $exceptions->renderable(fn (ProfessionalApplicationAlreadyPendingException $exception) => redirect()
            ->back()
            ->withErrors(['id_type' => $exception->getMessage()])
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
                $e instanceof AuthenticationException => ApiController::error('Unauthenticated.', 401),
                $e instanceof MethodNotAllowedHttpException => ApiController::error('Method not allowed.', 405),
                $e instanceof NotFoundHttpException => ApiController::error('Not found.', 404),
                default => null,
            };
        });

    })->create();
