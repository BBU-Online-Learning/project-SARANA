<?php

use App\Http\Middleware\RequireTwoFactorSetup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->trimStrings(except: [
            fn (Request $request): bool => $request->is('chat/calls/*/signal'),
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountIsActive::class,
            \App\Http\Middleware\EnforceAccountOnboarding::class,
        ]);

        // This gives you a simple route alias called twofactor.setup.
        $middleware->alias([
            'twofactor.setup' => RequireTwoFactorSetup::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'code', 'secret', 'token']);
    })
    ->booting(function () {
        // 🛠️ DEFINE YOUR "messages" THROTTLE HERE
        RateLimiter::for('messages', function (Request $request) {
            // Example: Allow 60 requests per minute per authenticated user
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('call-start', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('call-signals', function (Request $request) {
            return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
        });
    })
    ->create();
