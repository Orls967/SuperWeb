<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\LifeOfMineModel;
use Modules\Mining\Domain\Models\ResourceCommandSnapshot;
use Modules\Mining\Domain\Models\RiskHeatmapAlert;
use Modules\Shared\Application\BaseAction;

class CoordinateIntegratedResourceCommandCenterSlaAction extends BaseAction
{
    /**
     * Coordinate integrated Pit-to-Port command center, compute mine-to-market margin, model LoM, and monitor risk heatmap.
     *
     * @return array{snapshot: ResourceCommandSnapshot, lom_model: LifeOfMineModel, risk_alert: RiskHeatmapAlert, net_margin_idr: int, lom_years: float}
     */
    public function handle(
        string $siteId,
        float $pitExtractionTon = 15000.0,
        float $plantProcessingTon = 12000.0,
        float $terminalLoadedTon = 85000.0,
        int $liveCommodityPriceUsd = 1850,
        int $realizedRevenuePerTonIdr = 1800000,
        int $operatingCostPerTonIdr = 1200000,
        float $provenReservesTon = 150000000.0,
        float $annualRunRateTon = 5000000.0,
        float $priceVariancePct = -12.5,
        float $covenantThresholdVariancePct = -15.0
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $pitExtractionTon,
            $plantProcessingTon,
            $terminalLoadedTon,
            $liveCommodityPriceUsd,
            $realizedRevenuePerTonIdr,
            $operatingCostPerTonIdr,
            $provenReservesTon,
            $annualRunRateTon,
            $priceVariancePct,
            $covenantThresholdVariancePct
        ) {
            // 125.1 & 125.2 Pit-to-Port Resource Command & Mine-to-Market Margin
            $realizedRevenueMinor = (int) round($pitExtractionTon * $realizedRevenuePerTonIdr);
            $totalOperatingCostMinor = (int) round($pitExtractionTon * $operatingCostPerTonIdr);
            $netMarginMinor = $realizedRevenueMinor - $totalOperatingCostMinor;

            $snapshot = ResourceCommandSnapshot::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'snapshot_date' => Carbon::now()->toDateString(),
                'pit_production_tonnage' => $pitExtractionTon,
                'plant_processing_tonnage' => $plantProcessingTon,
                'terminal_loaded_tonnage' => $terminalLoadedTon,
                'live_commodity_price_usd' => (string) $liveCommodityPriceUsd,
                'fleet_utilization_rate_pct' => 88.5,
                'realized_revenue_minor' => $realizedRevenueMinor,
                'total_operating_cost_minor' => $totalOperatingCostMinor,
                'net_mine_to_market_margin_minor' => $netMarginMinor,
            ]);

            // 125.3 Reserve & Life-of-Mine (LoM) Depletion Model
            $lomYears = ($annualRunRateTon > 0) ? round($provenReservesTon / $annualRunRateTon, 1) : 0.0;

            $modelCode = sprintf('LOM-%s-%s', $siteId, date('Y'));
            $lomModel = LifeOfMineModel::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'model_code' => $modelCode,
                'proven_reserves_tonnage' => $provenReservesTon,
                'annual_run_rate_tonnage' => $annualRunRateTon,
                'life_of_mine_years' => $lomYears,
                'projected_expansion_capex_minor' => 75000000000,
                'capex_dao_approved' => true,
            ]);

            // 125.4 Integrated Risk Heatmap Trigger
            $isBreach = ($priceVariancePct <= $covenantThresholdVariancePct);
            $riskAlert = RiskHeatmapAlert::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'risk_type' => 'COMMODITY_PRICE_VOLATILITY',
                'threshold_pct' => $covenantThresholdVariancePct,
                'actual_variance_pct' => $priceVariancePct,
                'severity' => $isBreach ? 'CRITICAL_COVENANT_BREACH' : 'MODERATE_MONITORING',
                'remediation_action' => $isBreach ? 'EXECUTE_OFFTAKE_COLLATERAL_CALL' : 'HOLD_STRATEGY',
                'c_suite_notified' => $isBreach,
                'triggered_at' => Carbon::now(),
            ]);

            $this->audit(
                action: 'mining.command_center.coordinated',
                auditable: $snapshot,
                oldValues: null,
                newValues: [
                    'site_id' => $siteId,
                    'net_margin_minor' => $netMarginMinor,
                    'lom_years' => $lomYears,
                    'risk_severity' => $riskAlert->severity,
                ],
                correlationId: "min_cmd_{$snapshot->id}",
                impactType: 'resource_command_center'
            );

            return [
                'snapshot' => $snapshot,
                'lom_model' => $lomModel,
                'risk_alert' => $riskAlert,
                'net_margin_idr' => $netMarginMinor,
                'lom_years' => $lomYears,
            ];
        });
    }
}
