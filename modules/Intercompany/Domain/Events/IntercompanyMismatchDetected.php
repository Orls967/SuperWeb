<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntercompanyMismatchDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $pairKey,
        public readonly string $sellerEntityCode,
        public readonly string $buyerEntityCode,
        public readonly int $sellerBalanceMinor,
        public readonly int $buyerBalanceMinor,
        public readonly int $discrepancyMinor,
        public readonly string $detectedAt
    ) {}

    public function eventName(): string
    {
        return 'intercompany.mismatch.detected.v1';
    }
}
