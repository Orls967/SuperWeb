<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use DomainException;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreApiClientKey;

class ApiClientLifecycleService
{
    /**
     * @param string $tenantId
     * @param string $name
     * @param string|null $code
     * @param string|null $clientCode
     * @param string|null $legalEntityName
     * @param string $tier
     * @param array<int, string> $abilities
     * @param int|null $capacityOverride
     * @param int|null $rateOverride
     * @param int|null $rateLimitPerMinute
     * @param int|null $burstLimit
     * @param int|null $createdById
     * @return array{client: CoreApiClient, key: CoreApiClientKey, raw_key: string, plain_key: string}
     */
    public function provisionClient(
        string $tenantId,
        string $name,
        ?string $code = null,
        ?string $clientCode = null,
        ?string $legalEntityName = null,
        string $tier = 'SILVER',
        array $abilities = ['*'],
        ?int $capacityOverride = null,
        ?int $rateOverride = null,
        ?int $rateLimitPerMinute = null,
        ?int $burstLimit = null,
        ?int $createdById = null,
    ): array {
        $effectiveCode = $code ?? $clientCode ?? ('cli_' . Str::random(10));
        $tierDefaults = CoreApiClient::TIER_DEFAULTS[$tier] ?? CoreApiClient::TIER_DEFAULTS['SILVER'];
        $capacity = $capacityOverride ?? $burstLimit ?? $tierDefaults['capacity'];
        $refill = $rateOverride ?? ($rateLimitPerMinute ? (int) max(1, round($rateLimitPerMinute / 60)) : $tierDefaults['refill_per_sec']);
        $dailyQuota = $tierDefaults['daily_quota'];
        $bucketMilliTokens = $capacity * 1000;

        $nowMs = app()->bound(ClockInterface::class)
            ? (int) app(ClockInterface::class)->now()->getTimestampMs()
            : (int) round(microtime(true) * 1000);

        $client = CoreApiClient::create([
            'tenant_id' => $tenantId,
            'code' => $effectiveCode,
            'name' => $name,
            'legal_entity_name' => $legalEntityName ?? $name,
            'tier' => $tier,
            'capacity' => $capacity,
            'refill_per_sec' => $refill,
            'daily_quota' => $dailyQuota,
            'bucket_milli_tokens' => $bucketMilliTokens,
            'bucket_last_ms' => $nowMs,
            'is_active' => true,
        ]);

        $keyResult = $this->generateAndPersistKey($client, $abilities, $createdById);

        return [
            'client' => $client,
            'key' => $keyResult['key'],
            'raw_key' => $keyResult['raw_key'],
            'plain_key' => $keyResult['raw_key'],
        ];
    }

    /**
     * Rotate client API key. Allows at most two concurrently active keys during grace period (≤ 7 days).
     *
     * @param CoreApiClient $client
     * @param int|null $createdById
     * @param int $gracePeriodDays
     * @return array{client: CoreApiClient, key: CoreApiClientKey, raw_key: string}
     */
    public function rotateKey(CoreApiClient $client, ?int $createdById = null, int $gracePeriodDays = 7): array
    {
        $activeKeys = $client->keys()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('revoked_at')
                    ->where(function ($q): void {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->orderByDesc('id')
            ->get();

        if ($activeKeys->count() >= 2) {
            // Revoke oldest active key so max 2 keys rule is maintained
            $oldest = $activeKeys->last();
            $oldest->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);
        }

        $latestKey = $activeKeys->first();
        if ($latestKey !== null) {
            // Give old key an expiration grace period
            $latestKey->update([
                'expires_at' => now()->addDays(min($gracePeriodDays, 7)),
            ]);
        }

        $abilities = $latestKey?->abilities ?? ['*'];
        $keyResult = $this->generateAndPersistKey($client, $abilities, $createdById, $latestKey?->id);

        return [
            'client' => $client,
            'key' => $keyResult['key'],
            'raw_key' => $keyResult['raw_key'],
        ];
    }

    /**
     * @return array{client: CoreApiClient, key: CoreApiClientKey}|null
     */
    public function validateKey(string $rawKey): ?array
    {
        if (trim($rawKey) === '') {
            return null;
        }

        $digest = hash('sha256', $rawKey);

        /** @var CoreApiClientKey|null $key */
        $key = CoreApiClientKey::with('client')
            ->where('key_digest', $digest)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($key === null || $key->client === null || !$key->client->is_active) {
            return null;
        }

        return [
            'client' => $key->client,
            'key' => $key,
        ];
    }

    public function revokeKey(CoreApiClientKey $key, ?string $reason = null): void
    {
        $key->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }

    /**
     * @param CoreApiClient $client
     * @param array<int, string> $abilities
     * @param int|null $createdById
     * @param int|null $rotatedFromId
     * @return array{key: CoreApiClientKey, raw_key: string}
     */
    private function generateAndPersistKey(
        CoreApiClient $client,
        array $abilities,
        ?int $createdById = null,
        ?int $rotatedFromId = null,
    ): array {
        $prefix = 'sw_live_' . Str::random(8);
        $secret = Str::random(32);
        $rawKey = $prefix . '_' . $secret;
        $digest = hash('sha256', $rawKey);

        $key = CoreApiClientKey::create([
            'api_client_id' => $client->id,
            'rotated_from_id' => $rotatedFromId,
            'created_by_user_id' => $createdById,
            'key_prefix' => $prefix,
            'key_digest' => $digest,
            'abilities' => $abilities,
            'status' => 'active',
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        return [
            'key' => $key,
            'raw_key' => $rawKey,
        ];
    }
}
