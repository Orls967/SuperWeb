<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntercompanyConsolidationLocked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $fiscalPeriod,
        public readonly int $lockedByUserId,
        public readonly int $totalEliminatedMinor,
        public readonly ?string $lockedAt = null
    ) {}

    public function eventName(): string
    {
        return 'intercompany.consolidation.locked.v1';
    }
}
