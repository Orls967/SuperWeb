<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\ApiIdempotencyConflictException;
use Modules\Core\Domain\Models\CoreApiIdempotencyRecord;

class IdempotencyStoreService
{
    public const DEFAULT_TTL_HOURS = 24;

    /**
     * Compute deterministic hash of request payload.
     */
    public function computeRequestHash(Request $request): string
    {
        $canonical = implode('|', [
            strtoupper($request->method()),
            $request->path(),
            json_encode($request->query(), JSON_THROW_ON_ERROR),
            $request->getContent(),
        ]);

        return hash('sha256', $canonical);
    }

    /**
     * Find existing idempotency record or return null if none exists or has expired.
     * Throws ApiIdempotencyConflictException if key is reused with a different request hash.
     *
     * @return array{status: int, body: mixed, headers: array<string, mixed>}|null
     */
    public function check(
        string $clientCode,
        string $routeName,
        string $httpMethod,
        string $idempotencyKey,
        string $requestHash,
    ): ?array {
        /** @var CoreApiIdempotencyRecord|null $record */
        $record = CoreApiIdempotencyRecord::where('client_code', $clientCode)
            ->where('route_name', $routeName)
            ->where('http_method', strtoupper($httpMethod))
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($record === null) {
            return null;
        }

        if ($record->expires_at->isPast()) {
            $record->delete();
            return null;
        }

        if ($record->request_hash !== $requestHash) {
            throw new ApiIdempotencyConflictException(
                'IDEMP_MISMATCH: Idempotency-Key reuse with different request payload.'
            );
        }

        $decodedBody = json_decode($record->response_body, true);

        return [
            'status' => $record->response_status,
            'body' => $decodedBody ?? $record->response_body,
            'headers' => $record->headers ?? [],
        ];
    }

    /**
     * Store response for 24 hours.
     *
     * @param string $tenantId
     * @param string $clientCode
     * @param string $routeName
     * @param string $httpMethod
     * @param string $idempotencyKey
     * @param string $requestHash
     * @param int $responseStatus
     * @param mixed $responseBody
     * @param array<string, mixed> $headers
     * @param int $ttlHours
     */
    public function store(
        string $tenantId,
        string $clientCode,
        string $routeName,
        string $httpMethod,
        string $idempotencyKey,
        string $requestHash,
        int $responseStatus,
        mixed $responseBody,
        array $headers = [],
        int $ttlHours = self::DEFAULT_TTL_HOURS,
    ): CoreApiIdempotencyRecord {
        $bodyString = is_string($responseBody) ? $responseBody : json_encode($responseBody, JSON_UNESCAPED_SLASHES);

        return CoreApiIdempotencyRecord::updateOrCreate(
            [
                'client_code' => $clientCode,
                'route_name' => $routeName,
                'http_method' => strtoupper($httpMethod),
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'tenant_id' => $tenantId,
                'request_hash' => $requestHash,
                'response_status' => $responseStatus,
                'response_body' => $bodyString,
                'headers' => $headers,
                'expires_at' => now()->addHours($ttlHours),
            ]
        );
    }
}

