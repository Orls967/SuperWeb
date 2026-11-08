<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class MiceMasterFolioClosedEvent
{
    public function __construct(
        public readonly string $masterFolioId,
        public readonly string $contractNumber,
        public readonly int $finalSettledAmount,
        public readonly string $closedAt
    ) {}
}
