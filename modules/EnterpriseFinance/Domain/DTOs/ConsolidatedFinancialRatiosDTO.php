<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class ConsolidatedFinancialRatiosDTO
{
    public function __construct(
        public string $period,
        public int $currentRatioPermille,
        public int $quickRatioPermille,
        public int $cashRatioPermille,
        public int $debtToEquityPermille,
        public int $debtToAssetsPermille,
        public int $interestCoveragePermille,
        public int $ebitdaMarginBps,
        public int $netProfitMarginBps,
        public int $returnOnAssetsBps,
        public int $returnOnEquityBps,
        public int $assetTurnoverPermille
    ) {}

    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'current_ratio_permille' => $this->currentRatioPermille,
            'quick_ratio_permille' => $this->quickRatioPermille,
            'cash_ratio_permille' => $this->cashRatioPermille,
            'debt_to_equity_permille' => $this->debtToEquityPermille,
            'debt_to_assets_permille' => $this->debtToAssetsPermille,
            'interest_coverage_permille' => $this->interestCoveragePermille,
            'ebitda_margin_bps' => $this->ebitdaMarginBps,
            'net_profit_margin_bps' => $this->netProfitMarginBps,
            'return_on_assets_bps' => $this->returnOnAssetsBps,
            'return_on_equity_bps' => $this->returnOnEquityBps,
            'asset_turnover_permille' => $this->assetTurnoverPermille,
        ];
    }
}
