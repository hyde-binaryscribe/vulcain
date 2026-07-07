<?php

namespace App\Providers;

use App\Domain\Identity\LoginThrottle;
use App\Domain\Identity\PasswordResetService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginThrottle::class, fn () => new LoginThrottle(
            maxAttempts: (int) config('security.login.max_attempts', 5),
            decayMinutes: (int) config('security.login.decay_minutes', 15),
        ));

        $this->app->singleton(PasswordResetService::class, fn () => new PasswordResetService(
            expiresMinutes: (int) config('security.password_reset.expires_minutes', 60),
            throttleSeconds: (int) config('security.password_reset.throttle_seconds', 60),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Garde-fou HTTP complémentaire au blocage applicatif (table login_attempts).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
