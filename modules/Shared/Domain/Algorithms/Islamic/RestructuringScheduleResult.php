<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

final readonly class RestructuringScheduleResult
{
    /**
     * @param  array<int, array{month: int, is_grace: bool, principal_minor: int, margin_minor: int, installment_minor: int, remaining_principal_minor: int, remaining_margin_minor: int}>  $scheduleTable
     */
    public function __construct(
        public string $financingRef,
        public int $originalPrincipalMinor,
        public int $originalMarginMinor,
        public int $marginHaircutBps,
        public int $revisedPrincipalMinor,
        public int $revisedMarginMinor,
        public int $totalRestructuredDebtMinor,
        public int $newTenorMonths,
        public int $gracePeriodMonths,
        public int $monthlyPrincipalInstallmentMinor,
        public int $monthlyMarginInstallmentMinor,
        public int $monthlyTotalInstallmentMinor,
        public array $scheduleTable,
        public string $certificateHash,
    ) {}
}
