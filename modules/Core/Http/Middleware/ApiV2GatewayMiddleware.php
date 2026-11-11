<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Application\Api\ApiClientLifecycleService;
use Modules\Core\Application\Api\IdempotencyStoreService;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Algorithms\Api\IntegerTokenBucket;
use Modules\Core\Domain\Exceptions\ApiIdempotencyConflictException;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreApiUsageEvent;
use Symfony\Component\HttpFoundation\Response;

class ApiV2GatewayMiddleware
{
    public function __construct(
        private readonly ApiClientLifecycleService $clientService,
        private readonly IdempotencyStoreService $idempotencyStore,
        private readonly IntegerTokenBucket $tokenBucket,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $rawBearer = $request->bearerToken();
        $client = null;
        $clientKey = null;

        if ($rawBearer !== null && trim($rawBearer) !== '') {
            $validated = $this->clientService->validateKey($rawBearer);
            if ($validated !== null) {
                $client = $validated['client'];
                $clientKey = $validated['key'];
            }
        }

        $user = Auth::user() ?? $request->user();
        if ($client === null && $user === null) {
            return $this->rfc7807(
                status: 401,
                type: 'https://superweb.example.com/probs/unauthorized',
                title: 'Unauthorized',
                detail: 'Valid Bearer API key or authenticated user token is required.'
            );
        }

        // Sanctum token ability checking
        if ($user !== null && $client === null) {
            $token = $user->currentAccessToken();
            if ($token !== null && method_exists($token, 'can')) {
                $method = strtoupper($request->method());
                $requiredAbility = $method === 'GET' ? 'resource:read' : 'resource:action';
                if (!$token->can('*') && !$token->can($requiredAbility)) {
                    return $this->rfc7807(
                        status: 403,
                        type: 'https://superweb.example.com/probs/forbidden',
                        title: 'Forbidden',
                        detail: "Token lacks required ability [{$requiredAbility}]."
                    );
                }
            }
        }

        $tenantId = (string) ($request->header('X-Tenant-ID') ?? $client?->tenant_id ?? $user->tenant_id ?? 'tenant-default');
        $clientCode = $client?->code ?? ($user ? 'usr_' . $user->id : 'anon');

        // Tenancy Context binding
        $context = new TenantContext(
            tenantId: $tenantId,
            allowedTenantIds: [$tenantId]
        );
        TenantContext::push($context);

        try {
            // 1. Rate Limiting via IntegerTokenBucket (B1)
            if ($client !== null) {
                $client = $client->fresh();
                $clock = app()->bound(ClockInterface::class) ? app(ClockInterface::class) : null;
                $nowMs = $clock ? (int) $clock->now()->getTimestampMs() : (int) round(microtime(true) * 1000);

                $bucketResult = $this->tokenBucket->consume(
                    currentMilliTokens: (int) $client->bucket_milli_tokens,
                    lastMs: (int) $client->bucket_last_ms,
                    nowMs: $nowMs,
                    capacity: (int) $client->capacity,
                    ratePerSec: (int) $client->refill_per_sec,
                    costTokens: 1
                );

                $client->update([
                    'bucket_milli_tokens' => $bucketResult['remaining_milli_tokens'],
                    'bucket_last_ms' => $nowMs,
                ]);

                if (!$bucketResult['allowed']) {
                    $this->logUsage($client, $tenantId, $request, 429, false, $bucketResult['retry_after_ms']);

                    return response()->json([
                        'type' => 'https://superweb.example.com/probs/rate-limit-exceeded',
                        'title' => 'Too Many Requests',
                        'status' => 429,
                        'detail' => "Rate limit exceeded. Please retry after {$bucketResult['retry_after_seconds']} seconds.",
                        'retry_after_ms' => $bucketResult['retry_after_ms'],
                    ], 429, [
                        'Retry-After' => (string) $bucketResult['retry_after_seconds'],
                        'Content-Type' => 'application/problem+json',
                        'X-RateLimit-Remaining' => '0',
                    ]);
                }
            }

            // 2. Idempotency handling on state-mutating requests
            $method = strtoupper($request->method());
            $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            $idempotencyKey = $request->header('Idempotency-Key');
            $requestHash = '';

            if ($isMutating) {
                if ($idempotencyKey === null || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 255) {
                    return $this->rfc7807(
                        status: 422,
                        type: 'https://superweb.example.com/probs/invalid-idempotency-key',
                        title: 'Invalid Idempotency Key',
                        detail: 'Idempotency-Key header is required for mutating requests.'
                    );
                }

                $routeName = $request->route()?->getName() ?? $request->path();
                $requestHash = $this->idempotencyStore->computeRequestHash($request);

                try {
                    $cached = $this->idempotencyStore->check(
                        $clientCode,
                        $routeName,
                        $method,
                        $idempotencyKey,
                        $requestHash
                    );

                    if ($cached !== null) {
                        return response()->json($cached['body'], $cached['status'], $cached['headers'])
                            ->header('X-Idempotent-Replay', 'true');
                    }
                } catch (ApiIdempotencyConflictException $e) {
                    return $this->rfc7807(
                        status: 409,
                        type: 'https://superweb.example.com/probs/idempotency-conflict',
                        title: 'Idempotency Key Conflict',
                        detail: $e->getMessage()
                    );
                }
            }

            // 3. Process request
            $response = $next($request);

            // 4. Usage logging
            $this->logUsage($client, $tenantId, $request, $response->getStatusCode(), true, 0);

            // 5. Store idempotent response
            if ($isMutating && $idempotencyKey !== null && $response instanceof JsonResponse) {
                $routeName = $request->route()?->getName() ?? $request->path();
                $this->idempotencyStore->store(
                    $tenantId,
                    $clientCode,
                    $routeName,
                    $method,
                    $idempotencyKey,
                    $requestHash,
                    $response->getStatusCode(),
                    $response->getData(true)
                );
            }

            $response->headers->set('X-API-Version', 'v2');

            return $response;
        } finally {
            TenantContext::pop();
        }
    }

    private function logUsage(
        ?CoreApiClient $client,
        string $tenantId,
        Request $request,
        int $httpStatus,
        bool $allowed,
        int $retryAfterMs,
    ): void {
        if ($client === null) {
            return;
        }

        try {
            CoreApiUsageEvent::create([
                'api_client_id' => $client->id,
                'tenant_id' => $tenantId,
                'route_uri' => mb_substr($request->path(), 0, 160),
                'http_method' => strtoupper($request->method()),
                'http_status' => $httpStatus,
                'allowed' => $allowed,
                'retry_after_ms' => $retryAfterMs,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function rfc7807(
        int $status,
        string $type,
        string $title,
        string $detail,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status, array_merge(['Content-Type' => 'application/problem+json'], $headers));
    }
}
