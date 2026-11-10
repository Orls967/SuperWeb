<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class BudgetVarianceInputDTO
{
    public function __construct(
        public readonly string $departmentCode,
        public readonly string $fiscalYear,
        public readonly int $allocatedBudgetMinor,
        public readonly int $actualSpentMinor,
        public readonly int $encumberedMinor = 0,
        public readonly int $varianceBreachThresholdPermille = 100
    ) {}
}
