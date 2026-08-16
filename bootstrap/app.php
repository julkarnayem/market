<?php

use App\Http\Middleware\DenyFraming;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureUserCanSell;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global web middleware — security headers on every response
        $middleware->web(append: [SetSecurityHeaders::class]);

        // Inertia: shares props (auth user, flash, Ziggy routes) and handles
        // version/redirect negotiation. Appended to the web group so it runs
        // after session + CSRF, which share() depends on.
        $middleware->web(append: [HandleInertiaRequests::class]);

        // Route aliases
        $middleware->alias([
            'active'    => EnsureUserIsActive::class,
            'can_sell'  => EnsureUserCanSell::class,
            'admin'     => EnsureAdmin::class,
            'noindex'   => NoIndex::class,
            'no_frame'  => DenyFraming::class,
        ]);

        // // Named rate limiters
        // RateLimiter::for('login', fn(Request $r) =>
        //     Limit::perMinute(10)->by($r->input('email', $r->ip())));

        // RateLimiter::for('register', fn(Request $r) =>
        //     Limit::perMinute(5)->by($r->ip()));

        // RateLimiter::for('password-reset', fn(Request $r) =>
        //     Limit::perMinute(5)->by($r->input('email', $r->ip())));

        // RateLimiter::for('api', fn(Request $r) =>
        //     Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom JSON responses for common HTTP exceptions
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $r) {
            if ($r->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $r) {
            if ($r->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        });
    })->create();
