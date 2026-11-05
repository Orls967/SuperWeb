<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

final readonly class GigCreditLimitResult
{
    /**
     * @param  array{payload: array<string, mixed>, hash: string, created_at: string}  $certificate
     */
    public function __construct(
        public int $averageMonthlyIncome,
        public int $incomeVolatilityBps,
        public int $adjustedDsrBps,
        public int $maxMonthlyInstallment,
        public int $maxCreditLimit,
        public string $riskGrade,
        public bool $isEligible,
        public array $certificate
    ) {}
}
