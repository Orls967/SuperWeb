<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Venue\Domain\Events\ScalperAccountBlacklistedEvent;

final class HandleScalperBlacklistAuditListener
{
    public function handle(ScalperAccountBlacklistedEvent $event): void
    {
        Log::warning('Security audit: scalper account blacklisted and access revoked', [
            'log_id' => $event->log->id,
            'user_id' => $event->userId,
            'reason' => $event->reason,
            'velocity_rpm' => $event->velocityRequestsPerMinute,
            'ip' => $event->ipAddress,
        ]);
    }
}
