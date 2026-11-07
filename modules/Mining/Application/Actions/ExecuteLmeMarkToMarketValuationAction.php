<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Contracts\MetalsTradingLmeGateway;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;
use Modules\Mining\Domain\Models\MiningMetalsTradingPosition;
use Modules\Mining\Ledger\MiningFase121LedgerPostingService;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LmeMarketQuote;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LmeMetalMarkToMarketResult;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LmeMetalMarkToMarketValuator;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LmeMetalMarkToMarketVerifier;

final class ExecuteLmeMarkToMarketValuationAction
{
    public function __construct(
        private readonly LmeMetalMarkToMarketValuator $valuator,
        private readonly LmeMetalMarkToMarketVerifier $verifier,
        private readonly MetalsTradingLmeGateway $lmeGateway,
        private readonly MiningFase121LedgerPostingService $ledgerPostingService
    ) {}

    public function execute(
        MiningMetalsTradingContract $contract,
        string $positionType = 'SHORT',
        ?int $marketPriceUsdCents = null
    ): LmeMetalMarkToMarketResult {
        $priceQuote = $this->lmeGateway->fetchCashSettlementPrice($contract->metal_symbol);
        $fxRate = $this->lmeGateway->fetchExchangeRateUsdIdr();

        $cashPrice = $marketPriceUsdCents ?? $priceQuote['cash_cents_ton'];

        $quote = new LmeMarketQuote(
            metalSymbol: $contract->metal_symbol,
            cashPriceUsdCentsPerTon: $cashPrice,
            threeMonthPriceUsdCentsPerTon: $cashPrice + 12000,
            exchangeRateIdrPerUsd: $fxRate,
            annualizedInterestRatePct: 5.25,
            historicalVolatilityPct: 22.0
        );

        $result = $this->valuator->valuePosition(
            positionType: $positionType,
            metricTons: (float) $contract->contract_quantity_metric_tons,
            entryPriceUsdCentsPerTon: $contract->fixed_price_usd_cents_per_ton,
            quote: $quote,
            qualityGradeAdjustmentFactor: 1.0,
            freightAdjustmentUsdCentsPerTon: 0
        );

        // Verifikasi hasil MTM
        $this->verifier->verify($result, $quote);

        // Catat posisi harian Mark-to-Market
        $positionCode = 'POS-'.$contract->contract_number.'-'.now()->format('Ymd');
        $pos = MiningMetalsTradingPosition::updateOrCreate(
            ['position_code' => $positionCode],
            [
                'contract_id' => $contract->id,
                'valuation_date' => now()->toDateString(),
                'position_type' => $result->positionType,
                'open_lots' => round($result->metricTons / 25.0, 2),
                'metric_tons' => $result->metricTons,
                'entry_price_usd_cents_per_ton' => $result->entryPriceUsdCentsPerTon,
                'current_lme_settlement_cents_per_ton' => $result->currentMarketPriceUsdCentsPerTon,
                'unrealized_pnl_usd_cents' => $result->unrealizedPnlUsdCents,
                'unrealized_pnl_idr' => $result->unrealizedPnlIdr,
                'variation_margin_required_idr' => $result->variationMarginRequiredIdr,
                'margin_call_triggered' => $result->isMarginCallTriggered,
                'status' => 'ACTIVE',
                'metadata' => [
                    'pnl_percentage' => $result->pnlPercentage,
                    'breakdown' => $result->valuationBreakdown,
                    'verification_hash' => $result->verificationHash,
                ],
            ]
        );

        // Posting realisasi MTM jika ada pergerakan material
        if ($result->unrealizedPnlIdr !== 0) {
            $isGain = $result->unrealizedPnlIdr > 0;
            $this->ledgerPostingService->postHedgingMarkToMarketSettlement(
                positionCode: $pos->position_code,
                pnlIdr: $result->unrealizedPnlIdr,
                isGain: $isGain,
                description: "Realisasi MTM posisi {$result->positionType} kontrak {$contract->contract_number}"
            );
        }

        return $result;
    }
}
