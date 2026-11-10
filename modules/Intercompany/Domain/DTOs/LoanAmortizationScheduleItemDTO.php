<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

final class LoanAmortizationScheduleItemDTO
{
    public function __construct(
        public readonly int $periodNumber,
        public readonly string $dueDate,
        public readonly int $beginningBalanceMinor,
        public readonly int $principalDueMinor,
        public readonly int $interestGrossMinor,
        public readonly int $taxWithheldMinor,
        public readonly int $interestNetMinor,
        public readonly int $cashDisbursedMinor,
        public readonly int $endingBalanceMinor
    ) {}
}
