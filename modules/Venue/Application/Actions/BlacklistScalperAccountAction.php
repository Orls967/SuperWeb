<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Venue\Domain\Models\ScalperBlacklistLog;

class BlacklistScalperAccountAction
{
    public function execute(
        int $userId,
        string $reasonCode,
        int $detectedVelocityBps = 0,
        ?string $ipAddress = null,
        ?string $deviceFingerprint = null,
        string $actionTaken = 'BLOCKED'
    ): ScalperBlacklistLog {
        return ScalperBlacklistLog::query()->create([
            'user_id' => $userId,
            'reason_code' => $reasonCode,
            'detected_velocity_bps' => $detectedVelocityBps,
            'ip_address' => $ipAddress,
            'device_fingerprint' => $deviceFingerprint,
            'action_taken' => $actionTaken,
        ]);
    }
}
