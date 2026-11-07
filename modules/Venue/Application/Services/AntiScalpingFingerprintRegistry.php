<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\Cache;

final class AntiScalpingFingerprintRegistry
{
    private const CACHE_PREFIX = 'ven_scalper_fp_';

    /**
     * Catat dan analisis riwayat fingerprint perangkat & IP pembeli.
     *
     * @return array{
     *   is_suspicious: bool,
     *   requests_last_minute: int,
     *   distinct_accounts_seen: int,
     *   fingerprint_hash: string
     * }
     */
    public function recordAndAnalyze(string $ipAddress, ?string $rawFingerprint, int $userId): array
    {
        $fingerprintHash = hash('sha256', ($rawFingerprint ?? 'ANONYMOUS').'|'.$ipAddress);
        $cacheKey = self::CACHE_PREFIX.$fingerprintHash;

        /** @var array{count: int, users: array<int, int>, first_seen: int}|null $data */
        $data = Cache::get($cacheKey);

        if ($data === null) {
            $data = [
                'count' => 1,
                'users' => [$userId => 1],
                'first_seen' => time(),
            ];
            Cache::put($cacheKey, $data, 300); // 5 menit TTL
        } else {
            $data['count']++;
            $data['users'][$userId] = 1;
            Cache::put($cacheKey, $data, 300);
        }

        $distinctAccounts = count($data['users']);
        $isSuspicious = ($data['count'] > 45) || ($distinctAccounts > 3);

        return [
            'is_suspicious' => $isSuspicious,
            'requests_last_minute' => $data['count'],
            'distinct_accounts_seen' => $distinctAccounts,
            'fingerprint_hash' => $fingerprintHash,
        ];
    }
}
