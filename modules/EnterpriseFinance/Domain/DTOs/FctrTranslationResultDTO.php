<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class FctrTranslationResultDTO
{
    public function __construct(
        public string $entityCode,
        public string $foreignCurrency,
        public int $assetsIdr,
        public int $liabilitiesIdr,
        public int $equityHistoricalIdr,
        public int $netIncomeIdr,
        public int $fctrReserveIdr,
        public bool $isFctrSurplus,
        public bool $isBalanced
    ) {}

    public function toArray(): array
    {
        return [
            'entity_code' => $this->entityCode,
            'foreign_currency' => $this->foreignCurrency,
            'assets_idr' => $this->assetsIdr,
            'liabilities_idr' => $this->liabilitiesIdr,
            'equity_historical_idr' => $this->equityHistoricalIdr,
            'net_income_idr' => $this->netIncomeIdr,
            'fctr_reserve_idr' => $this->fctrReserveIdr,
            'is_fctr_surplus' => $this->isFctrSurplus,
            'is_balanced' => $this->isBalanced,
        ];
    }
}
