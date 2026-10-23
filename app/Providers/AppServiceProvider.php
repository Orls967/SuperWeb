<?php

declare(strict_types=1);

namespace App\Providers;

use App\Quality\Adapters\TwelveLinesAuditAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\TwelveLinesAuditPort;
use Modules\Integration\Domain\Models\ApiClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TwelveLinesAuditPort::class,
            TwelveLinesAuditAdapter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/components/enterprise'), 'enterprise');

        // D2: Aplikasi wajib gagal boot di production bila BLIND_INDEX_PEPPER kosong
        if ($this->app->environment('production') && empty(config('app.blind_index_pepper'))) {
            throw new \RuntimeException('BLIND_INDEX_PEPPER environment variable is required in production environment.');
        }

        // 57B.4 Granular rate limiters
        RateLimiter::for('transactions', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('wallet-pin', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-attempts', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email', $request->ip()));
        });

        RateLimiter::for('exports-imports', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // R3.4: B2B API Client Rate Limiting (K-23 / D6: key "api-client:{client_id}")
        $apiClientLimiter = function (Request $request) {
            $apiKey = $request->header('X-API-Key') ?? $request->bearerToken();
            $clientId = $request->header('X-Client-ID');

            $client = null;
            if ($apiKey) {
                $hash = hash('sha256', (string) $apiKey);
                $client = ApiClient::where('api_key_hash', $hash)->first();
            }

            if (! $client && $clientId) {
                $client = ApiClient::where('client_id', (string) $clientId)->first();
            }

            $id = $client?->client_id ?: ($clientId ?: $request->ip());
            $limit = (int) ($client?->rate_limit_per_minute ?: 600);

            return Limit::perMinute($limit)
                ->by($id)
                ->response(function (Request $request, array $headers) use ($limit, $id) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? 60);

                    return response()->json([
                        'message' => 'Too Many Requests: API rate limit exceeded.',
                        'client_id' => $id,
                        'limit_per_minute' => $limit,
                        'retry_after' => $retryAfter,
                    ], 429, array_merge($headers, [
                        'Retry-After' => (string) $retryAfter,
                    ]));
                });
        };

        RateLimiter::for('api-client', $apiClientLimiter);
        RateLimiter::for('partner-api', $apiClientLimiter);
    }
}
