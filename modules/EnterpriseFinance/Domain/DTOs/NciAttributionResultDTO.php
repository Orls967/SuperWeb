<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class NciAttributionResultDTO
{
    public function __construct(
        public string $subsidiaryEntity,
        public int $nciOwnershipPermille,
        public int $parentOwnershipPermille,
        public int $subsidiaryNetIncomeIdr,
        public int $parentProfitShareIdr,
        public int $nciProfitShareIdr,
        public int $subsidiaryNetAssetsIdr,
        public int $parentEquityShareIdr,
        public int $nciEquityShareIdr,
        public bool $isZeroSumAttributed
    ) {}

    public function toArray(): array
    {
        return [
            'subsidiary_entity' => $this->subsidiaryEntity,
            'nci_ownership_permille' => $this->nciOwnershipPermille,
            'parent_ownership_permille' => $this->parentOwnershipPermille,
            'subsidiary_net_income_idr' => $this->subsidiaryNetIncomeIdr,
            'parent_profit_share_idr' => $this->parentProfitShareIdr,
            'nci_profit_share_idr' => $this->nciProfitShareIdr,
            'subsidiary_net_assets_idr' => $this->subsidiaryNetAssetsIdr,
            'parent_equity_share_idr' => $this->parentEquityShareIdr,
            'nci_equity_share_idr' => $this->nciEquityShareIdr,
            'is_zero_sum_attributed' => $this->isZeroSumAttributed,
        ];
    }
}
