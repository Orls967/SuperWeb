<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Egy\Application\Actions\ExecuteBessArbitrageAction;
use Modules\Egy\Application\Actions\ExecuteBlackStartSequenceAction;
use Modules\Egy\Application\Actions\ProcessDemandResponseCurtailmentAction;
use Modules\Egy\Application\Actions\RecordGeneratorTestRunAction;
use Modules\Egy\Application\Actions\TransitionMicrogridIslandAction;
use Modules\Egy\Domain\Models\BessAsset;
use Modules\Egy\Domain\Models\BessDispatch;
use Modules\Egy\Domain\Models\BlackStartPlan;
use Modules\Egy\Domain\Models\DemandResponseEvent;
use Modules\Egy\Domain\Models\GeneratorLog;
use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Domain\Models\MicrogridFeeder;
use Modules\Shared\Domain\Algorithms\Utility\MicrogridLoadSheddingResult;

/**
 * Layanan Terpadu Manajemen Microgrid, Penyimpanan Energi BESS, dan Ketahanan Daya.
 */
class MicrogridAndResilienceService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected TransitionMicrogridIslandAction $islandAction,
        protected ExecuteBessArbitrageAction $bessAction,
        protected RecordGeneratorTestRunAction $generatorAction,
        protected ProcessDemandResponseCurtailmentAction $curtailmentAction,
        protected ExecuteBlackStartSequenceAction $blackStartAction,
    ) {}

    /**
     * Daftarkan microgrid baru.
     */
    public function registerMicrogrid(
        string $microgridCode,
        string $siteName,
        int $totalCapacityKw,
        bool $isBlackStartCapable = true
    ): Microgrid {
        return Microgrid::firstOrCreate(
            ['microgrid_code' => $microgridCode],
            [
                'site_name' => $siteName,
                'operating_mode' => 'grid_connected',
                'total_capacity_kw' => $totalCapacityKw,
                'current_generation_kw' => $totalCapacityKw,
                'current_load_kw' => 0,
                'grid_frequency_mhz' => 50000,
                'bus_voltage_volt' => 20000,
                'is_black_start_capable' => $isBlackStartCapable,
            ]
        );
    }

    /**
     * Tambah feeder beban microgrid.
     */
    public function addFeeder(
        int $microgridId,
        string $feederCode,
        string $feederName,
        string $tier,
        int $priorityRank,
        int $ratedLoadKw
    ): MicrogridFeeder {
        return MicrogridFeeder::firstOrCreate(
            [
                'microgrid_id' => $microgridId,
                'feeder_code' => $feederCode,
            ],
            [
                'feeder_name' => $feederName,
                'tier' => $tier,
                'priority_rank' => $priorityRank,
                'rated_load_kw' => $ratedLoadKw,
                'is_energized' => true,
            ]
        );
    }

    /**
     * Pemicu transisi islanding otomatis dengan pelepasan beban deterministik Knapsack (E13).
     */
    public function triggerIslanding(int $microgridId, int $availableGenerationKw): MicrogridLoadSheddingResult
    {
        return $this->islandAction->execute($microgridId, $availableGenerationKw);
    }

    /**
     * Daftarkan aset baterai BESS.
     */
    public function registerBessAsset(
        string $bessCode,
        string $siteName,
        int $capacityKwh,
        int $maxChargePowerKw,
        int $maxDischargePowerKw,
        string $batteryChemistry = 'LFP'
    ): BessAsset {
        return BessAsset::firstOrCreate(
            ['bess_code' => $bessCode],
            [
                'site_name' => $siteName,
                'battery_chemistry' => $batteryChemistry,
                'capacity_kwh' => $capacityKwh,
                'max_charge_power_kw' => $maxChargePowerKw,
                'max_discharge_power_kw' => $maxDischargePowerKw,
                'current_soc_bps' => 5000,
                'state_of_health_bps' => 10000,
                'cycle_count' => 0,
                'replacement_cost_idr' => $capacityKwh * 2500000, // Rp 2.5 juta per kWh
                'status' => 'operational',
            ]
        );
    }

    /**
     * Eksekusi arbitrase BESS 24 jam dengan pembukuan degradasi sel (E12).
     *
     * @param  list<int>  $hourlyTariffProfile
     */
    public function dispatchBessArbitrage(
        int $bessAssetId,
        array $hourlyTariffProfile,
        string $dispatchDate,
        string $idempotencyKey
    ): BessDispatch {
        return $this->bessAction->execute($bessAssetId, $hourlyTariffProfile, $dispatchDate, $idempotencyKey);
    }

    /**
     * Catat uji jalan mingguan / darurat genset diesel (Scope 1 BBM).
     */
    public function recordGeneratorTest(
        string $generatorAssetCode,
        string $siteName,
        string $runType,
        Carbon $startTime,
        int $durationMinutes,
        int $fuelConsumedLiters,
        int $loadPercentageBps,
        ?string $notes = null
    ): GeneratorLog {
        return $this->generatorAction->execute(
            generatorAssetCode: $generatorAssetCode,
            siteName: $siteName,
            runType: $runType,
            startTime: $startTime,
            durationMinutes: $durationMinutes,
            fuelConsumedLiters: $fuelConsumedLiters,
            loadPercentageBps: $loadPercentageBps,
            notes: $notes
        );
    }

    /**
     * Proses sinyal curtailment demand-response PLN.
     */
    public function handleDemandResponseCurtailment(
        string $eventCode,
        string $curtailmentSignalId,
        Carbon $startTime,
        int $targetCurtailmentKw,
        int $achievedCurtailmentKw,
        int $responseTimeSeconds
    ): DemandResponseEvent {
        return $this->curtailmentAction->execute(
            eventCode: $eventCode,
            curtailmentSignalId: $curtailmentSignalId,
            startTime: $startTime,
            targetCurtailmentKw: $targetCurtailmentKw,
            achievedCurtailmentKw: $achievedCurtailmentKw,
            responseTimeSeconds: $responseTimeSeconds
        );
    }

    /**
     * Rencanakan dan eksekusi sekuensial black-start pemulihan total blackout (E14).
     *
     * @param  list<array{code: string, name: string, dependencies: list<string>, step_duration_seconds: int, inrush_current_amp: int}>  $components
     */
    public function executeBlackStart(int $microgridId, array $components, string $planCode): BlackStartPlan
    {
        return $this->blackStartAction->execute($microgridId, $components, $planCode);
    }
}
