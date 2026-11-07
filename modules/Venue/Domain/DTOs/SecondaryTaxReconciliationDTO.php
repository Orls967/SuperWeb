<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class SecondaryTaxReconciliationDTO
{
    public function __construct(
        public readonly int $periodYear,
        public readonly int $periodMonth,
        public readonly int $totalTransactionsCount,
        public readonly int $grossSecondaryTurnoverIdr,
        public readonly int $totalPbjtTaxCollectedIdr,
        public readonly int $totalPlatformCommissionsIdr,
        public readonly int $totalPromoterRoyaltiesIdr,
        public readonly int $totalNetSellersPayoutIdr,
        public readonly bool $isConservationBalanced,
        public readonly int $checksumVarianceIdr,
        public readonly \DateTimeImmutable $reconciledAt,
    ) {
        if ($this->periodYear < 2020 || $this->periodYear > 2050) {
            throw new \InvalidArgumentException('periodYear tidak valid');
        }
        if ($this->periodMonth < 1 || $this->periodMonth > 12) {
            throw new \InvalidArgumentException('periodMonth harus antara 1 dan 12');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period_year' => $this->periodYear,
            'period_month' => $this->periodMonth,
            'total_transactions_count' => $this->totalTransactionsCount,
            'gross_secondary_turnover_idr' => $this->grossSecondaryTurnoverIdr,
            'total_pbjt_tax_collected_idr' => $this->totalPbjtTaxCollectedIdr,
            'total_platform_commissions_idr' => $this->totalPlatformCommissionsIdr,
            'total_promoter_royalties_idr' => $this->totalPromoterRoyaltiesIdr,
            'total_net_sellers_payout_idr' => $this->totalNetSellersPayoutIdr,
            'is_conservation_balanced' => $this->isConservationBalanced,
            'checksum_variance_idr' => $this->checksumVarianceIdr,
            'reconciled_at' => $this->reconciledAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
