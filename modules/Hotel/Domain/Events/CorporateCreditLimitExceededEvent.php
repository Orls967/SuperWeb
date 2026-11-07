<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

use Carbon\CarbonImmutable;

final class CorporateCreditLimitExceededEvent
{
    public readonly string $occurredAt;

    public function __construct(
        public readonly string $corporateAccountId,
        public readonly string $companyName,
        public readonly int $creditLimitIdr,
        public readonly int $requestedChargeIdr,
        public readonly int $currentOutstandingIdr
    ) {
        $this->occurredAt = CarbonImmutable::now()->toIso8601String();
    }
}
