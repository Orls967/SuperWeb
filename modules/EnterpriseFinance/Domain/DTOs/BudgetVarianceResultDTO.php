<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\BudgetVarianceCertificate;

final class BudgetVarianceResultDTO
{
    public function __construct(
        public readonly string $departmentCode,
        public readonly string $fiscalYear,
        public readonly int $allocatedBudgetMinor,
        public readonly int $totalCommittedMinor,
        public readonly int $varianceAmountMinor,
        public readonly int $utilizationPermille,
        public readonly int $variancePermille,
        public readonly string $classification,
        public readonly bool $isBreached,
        public readonly BudgetVarianceCertificate $certificate
    ) {}
}
