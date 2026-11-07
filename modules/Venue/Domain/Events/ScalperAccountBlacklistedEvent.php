<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\ScalperBlacklistLog;

final class ScalperAccountBlacklistedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ScalperBlacklistLog $log,
        public readonly int $userId,
        public readonly string $reason,
        public readonly int $velocityRequestsPerMinute,
        public readonly ?string $ipAddress,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
