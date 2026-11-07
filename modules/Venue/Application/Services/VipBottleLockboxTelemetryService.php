<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

final class VipBottleLockboxTelemetryService
{
    /**
     * Unlock electronic bottle cage for bartender upon valid order authorization.
     *
     * @return array{
     *     lockbox_code: string,
     *     cage_shelf_id: string,
     *     solenoid_unlocked: bool,
     *     auto_lock_timer_seconds: int,
     *     authorized_bartender_uid: string,
     *     unlock_timestamp: string
     * }
     */
    public function authorizeBottleCageAccess(
        string $lockboxCode,
        string $cageShelfId,
        string $bartenderUid,
        string $orderReceiptCode
    ): array {
        if (!str_starts_with($bartenderUid, 'BARTENDER-')) {
            throw new RuntimeException("Kredensial staf bar tidak sah: '{$bartenderUid}'. Akses brankas ditolak.");
        }

        return [
            'lockbox_code' => $lockboxCode,
            'cage_shelf_id' => $cageShelfId,
            'solenoid_unlocked' => true,
            'auto_lock_timer_seconds' => 45,
            'authorized_bartender_uid' => $bartenderUid,
            'unlock_timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Ingest door contact sensor telemetry to detect unauthorized cage forced entry.
     *
     * @return array{
     *     lockbox_code: string,
     *     tamper_detected: bool,
     *     alarm_broadcasted: bool,
     *     security_alert_code: ?string
     * }
     */
    public function monitorLockboxSensors(string $lockboxCode, bool $doorOpen, bool $isAuthorized): array
    {
        $tamper = $doorOpen && !$isAuthorized;
        $alertCode = null;

        if ($tamper) {
            $alertCode = 'ALARM-CAGE-FORCED-' . strtoupper(bin2hex(random_bytes(3)));
        }

        return [
            'lockbox_code' => $lockboxCode,
            'tamper_detected' => $tamper,
            'alarm_broadcasted' => $tamper,
            'security_alert_code' => $alertCode,
        ];
    }
}
