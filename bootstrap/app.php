<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\ThrottleAuthAttemptsMiddleware;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Middleware\ShareErrorsFromSession;

spl_autoload_register(function (string $class): void {
    $base = dirname(__DIR__);
    $prefixes = [
        'Modules\\' => $base.'/modules/',
        'App\\' => $base.'/app/',
        'Database\\Seeders\\' => $base.'/database/seeders/',
        'Database\\Factories\\' => $base.'/database/factories/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir.$relative.'.php';
            if (is_file($file)) {
                require_once $file;

                return;
            }
        }
    }
}, true, true);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
            ThrottleAuthAttemptsMiddleware::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'security.headers' => SecurityHeadersMiddleware::class,
            'throttle.auth' => ThrottleAuthAttemptsMiddleware::class,
        ]);

        $middleware->priority([
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            CheckRole::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->booted(function (): void {
        Gate::guessPolicyNamesUsing(function (string $class): string {
            if (preg_match('/^Modules\\\\([^\\\\]+)\\\\Domain\\\\Models\\\\([^\\\\]+)$/', $class, $matches)) {
                return 'Modules\\'.$matches[1].'\\Policies\\'.$matches[2].'Policy';
            }

            return 'App\\Policies\\'.class_basename($class).'Policy';
        });
    })->create();
