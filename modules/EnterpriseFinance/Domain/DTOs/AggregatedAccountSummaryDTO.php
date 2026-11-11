<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class AggregatedAccountSummaryDTO
{
    public function __construct(
        public readonly string $canonicalAccountCode,
        public readonly string $accountName,
        public readonly int $totalDebitMinor,
        public readonly int $totalCreditMinor,
        public readonly int $netBalanceMinor
    ) {}
}
