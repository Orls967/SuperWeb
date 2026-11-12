<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntercompanyMirrorPosted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $transactionCode,
        public readonly string $sellingEntityCode,
        public readonly string $buyingEntityCode,
        public readonly int $amountMinor,
        public readonly ?string $invoiceRef = null,
        public readonly ?string $billRef = null
    ) {}

    public function eventName(): string
    {
        return 'intercompany.mirror.posted.v1';
    }
}
