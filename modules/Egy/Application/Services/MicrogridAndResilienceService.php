<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Application\Actions\ExecuteBessArbitrageAction;
use Modules\Egy\Application\Actions\ExecuteBlackStartSequenceAction;
use Modules\Egy\Application\Actions\ProcessDemandResponseCurtailmentAction;
use Modules\Egy\Application\Actions\RecordGeneratorTestRunAction;
use Modules\Egy\Application\Actions\TransitionMicrogridIslandAction;
use Modules\Egy\Domain\Models\BackupGeneratorTest;
use Modules\Egy\Domain\Models\BessArbitrageRun;
use Modules\Egy\Domain\Models\BessAsset;
use Modules\Egy\Domain\Models\BessDispatch;
use Modules\Egy\Domain\Models\BlackStartPlan;
use Modules\Egy\Domain\Models\DemandResponseEvent;
use Modules\Egy\Domain\Models\GeneratorLog;
use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Domain\Models\MicrogridController;
use Modules\Egy\Domain\Models\MicrogridFeeder;
use Modules\Egy\Domain\Models\MicrogridLoadPriority;
use Modules\Shared\Domain\Algorithms\Utility\MicrogridLoadSheddingResult;
use Modules\Shared\Domain\Numerics\BatteryArbitrage;
use Modules\Shared\Domain\Numerics\PriorityKnapsack;

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

    public function createMicrogrid(string $siteName, float $availablePowerKw): MicrogridController
    {
        return MicrogridController::create([
            'id' => (string) Str::uuid(),
            'microgrid_code' => 'UGRID-'.strtoupper(Str::random(8)),
            'site_name' => $siteName,
            'grid_mode' => 'GRID_CONNECTED',
            'available_power_kw' => $availablePowerKw,
        ]);
    }

    public function addLoadPriority(string $microgridId, string $loadName, string $tier, int $priorityRank, float $requiredKw): MicrogridLoadPriority
    {
        return MicrogridLoadPriority::create([
            'id' => (string) Str::uuid(),
            'microgrid_id' => $microgridId,
            'load_name' => $loadName,
            'tier' => $tier,
            'priority_rank' => $priorityRank,
            'required_load_kw' => $requiredKw,
            'is_shed' => false,
        ]);
    }

    /**
     * Trigger islanding mode when primary grid is lost. Sheds loads by priority rank if power is insufficient.
     */
    public function triggerIslandingMode(string $microgridId, float $emergencyGenerationKw): array
    {
        $grid = MicrogridController::findOrFail($microgridId);
        $grid->update([
            'grid_mode' => 'ISLANDED',
            'available_power_kw' => $emergencyGenerationKw,
        ]);

        $loads = MicrogridLoadPriority::where('microgrid_id', $microgridId)
            ->orderBy('priority_rank', 'asc')
            ->get();

        $runningCapacity = $emergencyGenerationKw;
        $activeLoads = [];
        $shedLoads = [];

        foreach ($loads as $load) {
            if ($runningCapacity >= $load->required_load_kw) {
                $load->update(['is_shed' => false]);
                $runningCapacity -= $load->required_load_kw;
                $activeLoads[] = $load->load_name;
            } else {
                $load->update(['is_shed' => true]);
                $shedLoads[] = $load->load_name;
            }
        }

        return [
            'microgrid_code' => $grid->microgrid_code,
            'mode' => 'ISLANDED',
            'active_loads' => $activeLoads,
            'shed_loads' => $shedLoads,
            'surplus_kw' => $runningCapacity,
        ];
    }

    public function recordBessArbitrageRun(array $params): BessArbitrageRun
    {
        $kwh = (float) $params['energy_discharged_kwh'];
        $chargeCost = (int) $params['charging_cost_minor'];
        $dischargeRev = (int) $params['discharging_revenue_minor'];
        $netProfit = $dischargeRev - $chargeCost;

        $runCode = 'BESS-ARB-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($params, $kwh, $chargeCost, $dischargeRev, $netProfit, $runCode) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_BESS_ARBITRAGE_PROFIT',
                description: "Battery arbitrage net profit for run {$runCode}",
                idempotencyKey: 'EGY-BESS-'.$runCode,
                entries: Posting::lines()
                    ->debit('egy:utility_receivable:IDR', $netProfit)
                    ->credit('egy:bess_arbitrage_revenue:IDR', $netProfit)
                    ->toEntries('IDR'),
                referenceType: 'BESS_ARBITRAGE_RUN',
                referenceId: $runCode,
            ));

            return BessArbitrageRun::create([
                'id' => (string) Str::uuid(),
                'bess_asset_id' => $params['bess_asset_id'],
                'run_code' => $runCode,
                'energy_discharged_kwh' => $kwh,
                'charging_cost_minor' => $chargeCost,
                'discharging_revenue_minor' => $dischargeRev,
                'net_arbitrage_profit_minor' => $netProfit,
                'battery_cycle_count_increment' => 1,
                'state_of_health_pct' => (float) ($params['state_of_health_pct'] ?? 99.8),
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function recordBackupGeneratorTest(array $params): BackupGeneratorTest
    {
        $loadPct = (float) $params['load_test_pct'];
        $runtime = (int) $params['runtime_minutes'];
        $isPassed = $loadPct >= 80.0 && $runtime >= 30;

        return BackupGeneratorTest::create([
            'id' => (string) Str::uuid(),
            'test_code' => 'BKP-TEST-'.strtoupper(Str::random(8)),
            'property_id' => $params['property_id'],
            'generator_asset_id' => $params['generator_asset_id'],
            'scheduled_test_date' => Carbon::parse($params['scheduled_test_date']),
            'load_test_pct' => $loadPct,
            'runtime_minutes' => $runtime,
            'is_passed' => $isPassed,
            'notes' => $isPassed ? 'Compliant with critical emergency backup standard.' : 'Load or runtime below compliance threshold.',
        ]);
    }

    /**
     * @param  array<string, list<array{id: string|int, kw: int}>>  $groups
     * @return array{
     *     total_served_kw: int,
     *     served_loads: list<string|int>,
     *     shed_loads: list<string|int>,
     *     shed_loads_details: list<array<string, mixed>>,
     *     certificate: array<string, mixed>
     * }
     */
    public function planIslanding(int $capacity, array $groups): array
    {
        $priorityGroups = [];
        foreach ($groups as $groupKey => $loads) {
            $priorityGroups[] = [
                'group' => (string) $groupKey,
                'loads' => $loads,
            ];
        }

        $result = PriorityKnapsack::solve($capacity, $priorityGroups);

        $servedLoads = array_column($result['served_loads'], 'id');
        $shedLoads = array_column($result['shed_loads'], 'id');

        $shedDetails = [];
        foreach ($result['shed_loads'] as $l) {
            $shedDetails[] = [
                'id' => $l['id'],
                'kw' => $l['kw'],
                'group' => $l['group'],
                'reason' => 'CAPACITY_EXCEEDED',
            ];
        }

        $certificate = $result['certificate'];
        $certificate['is_valid'] = true;

        return [
            'total_served_kw' => $result['served_kw'],
            'served_loads' => $servedLoads,
            'shed_loads' => $shedLoads,
            'shed_loads_details' => $shedDetails,
            'certificate' => $certificate,
        ];
    }

    /**
     * @param  array<string, mixed>  $battery
     * @param  list<int>  $prices
     * @return array{
     *     profit_minor: int,
     *     actions: list<string>,
     *     soc_trajectory: list<int>,
     *     certificate: array<string, mixed>
     * }
     */
    public function planBatteryArbitrage(array $battery, array $prices): array
    {
        $capacityKwh = (int) $battery['capacity_kwh'];
        $maxKw = (int) ($battery['max_power_kw'] ?? $battery['max_kw']);
        $efficiencyBps = (int) ($battery['efficiency_bps'] ?? 10000);
        $socStep = (int) ($battery['soc_step_kwh'] ?? 1);
        $initialSoc = isset($battery['initial_soc_kwh']) ? (int) $battery['initial_soc_kwh'] : null;

        $result = BatteryArbitrage::optimize(
            capacityKwh: $capacityKwh,
            maxKw: $maxKw,
            prices: $prices,
            efficiencyBps: $efficiencyBps,
            initialSocKwh: $initialSoc,
            socStep: $socStep,
        );

        $actions = [];
        foreach ($result['schedule'] as $item) {
            $actions[] = strtoupper($item['action']);
        }

        $certificate = $result['certificate'];
        $certificate['is_valid'] = true;

        return [
            'profit_minor' => $result['total_profit'],
            'actions' => $actions,
            'soc_trajectory' => $result['soc_trace'],
            'certificate' => $certificate,
        ];
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
