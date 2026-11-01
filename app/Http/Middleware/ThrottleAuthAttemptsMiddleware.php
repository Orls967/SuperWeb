<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ThrottleAuthAttemptsMiddleware
{
    /**
     * Rate limit authentication endpoints (login, forgot-password, reset-password).
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decaySeconds = 60): Response
    {
        $isAuthEndpoint = $request->isMethod('POST') && ($request->is('login') || $request->is('forgot-password') || $request->is('reset-password'));

        if ($isAuthEndpoint) {
            $key = $this->resolveThrottleKey($request);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($key);

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Too many authentication attempts. Please try again later.',
                        'retry_after' => $retryAfter,
                    ], Response::HTTP_TOO_MANY_REQUESTS, [
                        'Retry-After' => (string) $retryAfter,
                    ]);
                }

                return response(
                    'Too many authentication attempts. Please try again later.',
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['Retry-After' => (string) $retryAfter]
                );
            }

            try {
                $response = $next($request);
            } catch (\Throwable $e) {
                RateLimiter::hit($key, $decaySeconds);

                throw $e;
            }

            if (Auth::check()) {
                RateLimiter::clear($key);
            } else {
                RateLimiter::hit($key, $decaySeconds);
            }

            return $response;
        }

        return $next($request);
    }

    public function resolveThrottleKey(Request $request): string
    {
        $email = (string) $request->input('email', '');
        $sanitizedEmail = Str::lower(trim($email));
        $ip = (string) $request->ip();

        return 'auth:throttle:'.sha1("{$ip}:{$sanitizedEmail}");
    }
}
