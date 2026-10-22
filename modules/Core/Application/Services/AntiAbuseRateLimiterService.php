<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AntiAbuseRateLimiterService
{
    /**
     * Verifikasi usia venue dengan biometrik simulasi & anti-bruteforce (100.5).
     *
     * @return array{allowed: bool, reason: string, attempts_remaining: int}
     */
    public function verifyVenueAge(
        string $visitorId,
        int $declaredAge,
        string $biometricFingerprint,
        int $minAge = 21
    ): array {
        $rateKey = "venue:age_verify:{$visitorId}";
        $attempts = (int) Cache::get($rateKey, 0);

        if ($attempts >= 5) {
            return [
                'allowed' => false,
                'reason' => 'RATE_LIMIT_EXCEEDED_BIOMETRIC_LOCKOUT',
                'attempts_remaining' => 0,
            ];
        }

        Cache::put($rateKey, $attempts + 1, now()->addMinutes(10));

        if (strlen($biometricFingerprint) < 32) {
            return [
                'allowed' => false,
                'reason' => 'INVALID_BIOMETRIC_HASH',
                'attempts_remaining' => max(0, 5 - ($attempts + 1)),
            ];
        }

        if ($declaredAge < $minAge) {
            return [
                'allowed' => false,
                'reason' => 'UNDERAGE_FORBIDDEN',
                'attempts_remaining' => max(0, 5 - ($attempts + 1)),
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'AGE_VERIFIED',
            'attempts_remaining' => max(0, 5 - ($attempts + 1)),
        ];
    }

    /**
     * Anti-bruteforce akses IGD (Unit Gawat Darurat) dengan profil terisolasi (100.5).
     *
     * @return array{is_blocked: bool, failure_count: int, reason: string}
     */
    public function evaluateIgdAccess(string $staffId, string $terminalIp, string $accessResult): array
    {
        $failKey = "igd:fail_count:{$terminalIp}:{$staffId}";
        $lockKey = "igd:lockout:{$terminalIp}";

        if (Cache::has($lockKey)) {
            return [
                'is_blocked' => true,
                'failure_count' => 5,
                'reason' => 'TERMINAL_IGD_LOCKED_OUT',
            ];
        }

        if ($accessResult !== 'SUCCESS') {
            $failures = (int) Cache::increment($failKey);
            Cache::put($failKey, $failures, now()->addMinutes(15));

            if ($failures >= 3) {
                Cache::put($lockKey, true, now()->addMinutes(15));

                return [
                    'is_blocked' => true,
                    'failure_count' => $failures,
                    'reason' => 'BRUTEFORCE_THRESHOLD_REACHED',
                ];
            }

            return [
                'is_blocked' => false,
                'failure_count' => $failures,
                'reason' => 'AUTH_FAILED',
            ];
        }

        Cache::forget($failKey);

        return [
            'is_blocked' => false,
            'failure_count' => 0,
            'reason' => 'ACCESS_GRANTED',
        ];
    }

    /**
     * Anti-scalping & booking massal untuk tiket konser venue & kamar hotel (100.5).
     *
     * @return array{allowed: bool, reason: string, allocated_qty: int}
     */
    public function evaluateAntiScalping(
        string $userId,
        string $resourceType,
        int $requestedQuantity,
        int $maxPerWindow = 4
    ): array {
        $windowKey = "scalp:window:{$resourceType}:{$userId}";
        $currentBooked = (int) Cache::get($windowKey, 0);

        if ($requestedQuantity <= 0) {
            return [
                'allowed' => false,
                'reason' => 'INVALID_QUANTITY',
                'allocated_qty' => 0,
            ];
        }

        if (($currentBooked + $requestedQuantity) > $maxPerWindow) {
            return [
                'allowed' => false,
                'reason' => 'ANTI_SCALPING_CAP_EXCEEDED',
                'allocated_qty' => 0,
            ];
        }

        Cache::put($windowKey, $currentBooked + $requestedQuantity, now()->addMinutes(5));

        return [
            'allowed' => true,
            'reason' => 'BOOKING_PERMITTED',
            'allocated_qty' => $requestedQuantity,
        ];
    }

    /**
     * Rotasi device token telematika IoT (100.5).
     *
     * @return array{status: string, new_token: string|null, expires_in_seconds: int}
     */
    public function rotateTelematicsToken(
        string $deviceId,
        string $suppliedOldToken
    ): array {
        $activeTokenKey = "telematics:device_token:{$deviceId}";
        $storedToken = Cache::get($activeTokenKey);

        // Jika belum ada token sama sekali (inisialisasi pertama) atau token cocok
        if ($storedToken === null || hash_equals((string) $storedToken, $suppliedOldToken)) {
            $newToken = 'tlm_'.Str::random(40);
            $ttlSeconds = 3600; // 1 hour token life

            Cache::put($activeTokenKey, $newToken, now()->addSeconds($ttlSeconds));

            // Simpan riwayat token sebelumnya dengan grace period 60 detik untuk transisi
            if ($storedToken !== null) {
                Cache::put("telematics:device_grace:{$deviceId}:{$storedToken}", true, now()->addSeconds(60));
            }

            return [
                'status' => 'ROTATED_SUCCESSFULLY',
                'new_token' => $newToken,
                'expires_in_seconds' => $ttlSeconds,
            ];
        }

        return [
            'status' => 'INVALID_OLD_TOKEN_REJECTED',
            'new_token' => null,
            'expires_in_seconds' => 0,
        ];
    }

    /**
     * Validasi token telematika IoT aktif atau dalam masa grace period (100.5).
     */
    public function validateTelematicsToken(string $deviceId, string $token): bool
    {
        $activeToken = Cache::get("telematics:device_token:{$deviceId}");
        if ($activeToken !== null && hash_equals((string) $activeToken, $token)) {
            return true;
        }

        return Cache::has("telematics:device_grace:{$deviceId}:{$token}");
    }
}
