<?php

namespace App\Providers;

use App\Contracts\Kyc\FaceMatchClientContract;
use App\Contracts\Kyc\OcrClientContract;
use App\Services\Kyc\GoogleVisionKycClient;
use App\Services\Kyc\HttpKycSidecarClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OcrClientContract::class, fn () => match (config('kyc.ocr_driver')) {
            'sidecar' => app(HttpKycSidecarClient::class),
            default => app(GoogleVisionKycClient::class),
        });

        $this->app->bind(FaceMatchClientContract::class, fn () => match (config('kyc.face_driver')) {
            'sidecar' => app(HttpKycSidecarClient::class),
            default => app(GoogleVisionKycClient::class),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * General-purpose limiters for authenticated API routes that previously
     * had none (medical-information CRUD, device-keys, pending-sync,
     * emergency-qr, scans). Fortify's own limiters (`login`, `two-factor`,
     * `passkeys`) stay in `FortifyServiceProvider` - these are for
     * everything else under `auth:sanctum`.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Tighter limit for the bulk /sync pull - one call fetches
        // everything changed since a timestamp, so it doesn't need (and
        // shouldn't get) the same allowance as individual CRUD calls.
        RateLimiter::for('sync', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
