<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

use Carbon\CarbonImmutable;

final class TimeshareDividendPaidEvent
{
    public readonly string $occurredAt;

    public function __construct(
        public readonly string $timeshareUnitId,
        public readonly string $investorUserId,
        public readonly int $netDividendPaidIdr,
        public readonly int $maintenanceFeeDeductedIdr,
        public readonly string $ledgerAccountCredited
    ) {
        $this->occurredAt = CarbonImmutable::now()->toIso8601String();
    }
}
