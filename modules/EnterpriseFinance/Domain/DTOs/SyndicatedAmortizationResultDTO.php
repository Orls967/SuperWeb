<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class SyndicatedAmortizationResultDTO
{
    /**
     * @param  list<array{
     *     quarter: int,
     *     opening_principal_minor: int,
     *     principal_payment_minor: int,
     *     interest_rate_effective_bps: int,
     *     interest_payment_minor: int,
     *     undrawn_fee_minor: int,
     *     total_installment_minor: int,
     *     closing_principal_minor: int
     * }>  $schedule
     */
    public function __construct(
        public string $facilityCode,
        public string $currency,
        public int $principalTotalMinor,
        public int $totalQuarters,
        public int $totalInterestProjectedMinor,
        public int $totalServiceProjectedMinor,
        public array $schedule
    ) {}

    public function toArray(): array
    {
        return [
            'facility_code' => $this->facilityCode,
            'currency' => $this->currency,
            'principal_total_minor' => $this->principalTotalMinor,
            'total_quarters' => $this->totalQuarters,
            'total_interest_projected_minor' => $this->totalInterestProjectedMinor,
            'total_service_projected_minor' => $this->totalServiceProjectedMinor,
            'schedule' => $this->schedule,
        ];
    }
}
