<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Shared\Domain\Algorithms\Port\BunkerFuelConsumptionOptimizer;
use Modules\Shared\Domain\Algorithms\Port\TimeCharterEquivalentCalculator;

/**
 * Action Optimasi Kecepatan Slow Steaming & Penghematan Bahan Bakar Pelayaran.
 */
final class OptimizeVoyageSpeedAndBunkerAction
{
    public function __construct(
        private readonly BunkerFuelConsumptionOptimizer $bunkerOptimizer = new BunkerFuelConsumptionOptimizer,
        private readonly TimeCharterEquivalentCalculator $tceCalculator = new TimeCharterEquivalentCalculator
    ) {}

    public function execute(
        int $voyageId,
        float $cargoFreightRateUsdMt,
        float $cargoQuantityMt,
        float $currentVlsfoPriceUsdMt = 650.0,
        float $portDisbursementTotalUsd = 45000.0,
        float $dailyOpexUsd = 6500.0
    ): array {
        $voyage = FleetVoyage::with('vessel')->findOrFail($voyageId);
        $vessel = $voyage->vessel;

        $distance = (float) $voyage->planned_distance_nm;
        $displacement = (float) $vessel->deadweight_tonnage * 1.15; // Estimasi displasemen penuh
        $designSpeed = (float) $vessel->design_speed_knots;
        $designPower = (float) $vessel->main_engine_power_kw;

        // Bandingkan 3 profil kecepatan:
        // 1. Full Speed (14.0 kts)
        // 2. Eco Speed (12.5 kts)
        // 3. Super Slow Steaming (11.0 kts)
        $speedProfiles = [
            'FULL_SPEED' => min($designSpeed, 14.5),
            'ECO_SPEED' => max(9.0, $designSpeed * 0.85),
            'SLOW_STEAMING' => max(8.5, $designSpeed * 0.75),
        ];

        $analysis = [];
        $optimalProfile = '';
        $highestTce = -PHP_FLOAT_MAX;

        foreach ($speedProfiles as $label => $speed) {
            $voyageBunker = $this->bunkerOptimizer->calculateVoyageBunker(
                $distance,
                $speed,
                $displacement,
                $designSpeed,
                $designPower,
                5,
                2.0,
                2.0
            );

            $seaDays = $voyageBunker['steaming_days'];
            $seaBunkerCost = $voyageBunker['sea_bunker_mt'] * $currentVlsfoPriceUsdMt;
            $portBunkerCost = $voyageBunker['port_bunker_mt'] * $currentVlsfoPriceUsdMt;

            $tce = $this->tceCalculator->calculateTce(
                $cargoQuantityMt,
                $cargoFreightRateUsdMt,
                $seaDays,
                2.0,
                2.0,
                $seaBunkerCost,
                $portBunkerCost,
                $portDisbursementTotalUsd,
                0.0,
                0.0,
                0.0,
                0.0,
                0.0,
                $dailyOpexUsd
            );

            $analysis[$label] = [
                'speed_knots' => $speed,
                'steaming_days' => round($seaDays, 1),
                'total_bunker_mt' => $voyageBunker['total_voyage_bunker_mt'],
                'bunker_cost_usd' => round($seaBunkerCost + $portBunkerCost, 2),
                'daily_tce_usd' => $tce['daily_tce_usd'],
                'net_daily_profit_usd' => $tce['net_daily_profit_usd'],
            ];

            if ($tce['daily_tce_usd'] > $highestTce) {
                $highestTce = $tce['daily_tce_usd'];
                $optimalProfile = $label;
            }
        }

        return [
            'voyage_id' => $voyage->id,
            'distance_nm' => $distance,
            'optimal_profile' => $optimalProfile,
            'recommended_speed_knots' => $speedProfiles[$optimalProfile],
            'profiles_analysis' => $analysis,
        ];
    }
}
