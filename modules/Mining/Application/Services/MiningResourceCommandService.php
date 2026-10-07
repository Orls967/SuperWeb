<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\LifeOfMineModel;
use Modules\Mining\Domain\Models\ResourceCommandSnapshot;
use Modules\Mining\Domain\Models\RiskHeatmapAlert;
use RuntimeException;

class MiningResourceCommandService
{
    public function recordDailySnapshot(array $params): ResourceCommandSnapshot
    {
        $realizedRevenue = (int) $params['realized_revenue_minor'];
        $operatingCost = (int) $params['total_operating_cost_minor'];
        $netMargin = $realizedRevenue - $operatingCost;

        return ResourceCommandSnapshot::create([
            'id' => (string) Str::uuid(),
            'site_id' => $params['site_id'],
            'snapshot_date' => $params['snapshot_date'],
            'pit_production_tonnage' => (float) ($params['pit_production_tonnage'] ?? 0.0),
            'plant_processing_tonnage' => (float) ($params['plant_processing_tonnage'] ?? 0.0),
            'terminal_loaded_tonnage' => (float) ($params['terminal_loaded_tonnage'] ?? 0.0),
            'live_commodity_price_usd' => (float) ($params['live_commodity_price_usd'] ?? 0.0),
            'fleet_utilization_rate_pct' => (float) ($params['fleet_utilization_rate_pct'] ?? 85.0),
            'realized_revenue_minor' => $realizedRevenue,
            'total_operating_cost_minor' => $operatingCost,
            'net_mine_to_market_margin_minor' => $netMargin,
        ]);
    }

    public function calculateLifeOfMine(string $siteId, float $provenReservesTonnage, float $annualRunRateTonnage, int $projectedCapexMinor = 0): LifeOfMineModel
    {
        if ($annualRunRateTonnage <= 0) {
            throw new RuntimeException('Annual production run-rate must be strictly positive.');
        }

        $years = round($provenReservesTonnage / $annualRunRateTonnage, 2);

        return LifeOfMineModel::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteId,
            'model_code' => 'LOM-'.strtoupper(Str::random(8)),
            'proven_reserves_tonnage' => $provenReservesTonnage,
            'annual_run_rate_tonnage' => $annualRunRateTonnage,
            'life_of_mine_years' => $years,
            'projected_expansion_capex_minor' => $projectedCapexMinor,
            'capex_dao_approved' => false,
        ]);
    }

    public function evaluatePriceDropRisk(string $siteId, float $thresholdDropPct, float $actualDropPct): ?RiskHeatmapAlert
    {
        if ($actualDropPct >= $thresholdDropPct) {
            $severity = $actualDropPct >= 25.0 ? 'CRITICAL' : 'HIGH';

            return RiskHeatmapAlert::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'risk_type' => 'COMMODITY_PRICE_DROP',
                'threshold_pct' => $thresholdDropPct,
                'actual_variance_pct' => $actualDropPct,
                'severity' => $severity,
                'remediation_action' => 'Trigger Treasury FX/Metals hedging, curtail high-cost strip pits, alert C-suite.',
                'c_suite_notified' => true,
                'triggered_at' => Carbon::now(),
            ]);
        }

        return null;
    }
}
