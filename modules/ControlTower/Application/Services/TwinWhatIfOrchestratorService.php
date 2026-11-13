<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Algorithms\WhatIfTwinSimulator;
use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\TwinScenario;
use Modules\ControlTower\Infrastructure\Simulation\ControlTowerTwinTickHandler;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\SimulationRunner;
use RuntimeException;

final class TwinWhatIfOrchestratorService
{
    /** @var list<string> */
    public const SUPPORTED_SCENARIOS = [
        'PORT_CLOSURE',
        'MATERIAL_INFLATION',
        'SUPPLY_DELAY',
        'DEMAND_SPIKE',
        'SUPPLIER_LOSS',
        'WAREHOUSE_CAPACITY',
    ];

    /** @var list<string> */
    private const ALLOWED_SCENARIOS = [
        'PORT_CLOSURE',
        'MATERIAL_INFLATION',
        'SUPPLY_DELAY',
        'DEMAND_SPIKE',
        'SUPPLIER_LOSS',
        'WAREHOUSE_CAPACITY',
        'DEMAND_SURGE',
        'PLANT_EXPANSION',
    ];

    public function __construct(
        private readonly WhatIfTwinSimulator $simulator,
        private readonly ControlTowerTwinTickHandler $tickHandler,
        private readonly SimulationRunner $simulationRunner,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Menghitung digest SHA-256 deterministik atas 3 tabel operasional ControlTower
     * untuk membuktikan nol mutasi sebelum dan sesudah simulasi sandbox.
     */
    public function computeOperationalStateHash(): string
    {
        $echelonStocks = EchelonStock::query()
            ->orderBy('id')
            ->get([
                'id',
                'item_code',
                'echelon_node',
                'on_hand_qty',
                'in_transit_qty',
                'reserved_qty',
                'safety_stock_qty',
                'abc_class',
                'xyz_class',
            ])
            ->toArray();

        $orderPromises = OrderPromise::query()
            ->orderBy('id')
            ->get([
                'id',
                'promise_code',
                'order_reference',
                'item_code',
                'requested_qty',
                'atp_confirmed_qty',
                'ctp_manufacturing_qty',
                'promised_delivery_date',
                'promise_status',
            ])
            ->toArray();

        $disruptionAlerts = DisruptionAlert::query()
            ->orderBy('id')
            ->get([
                'id',
                'alert_code',
                'severity',
                'category',
                'title',
                'affected_orders_count',
                'status',
            ])
            ->toArray();

        $payload = json_encode([
            'sct_echelon_stocks' => $echelonStocks,
            'sct_order_promises' => $orderPromises,
            'sct_disruption_alerts' => $disruptionAlerts,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }

    /**
     * Menjalankan skenario what-if Supply Chain Digital Twin pada mode sandbox tanpa mutasi tabel operasional nyata.
     *
     * @return array{
     *     scenario: TwinScenario,
     *     scenario_id: int,
     *     scenario_code: string,
     *     name: string,
     *     shock_type: string,
     *     shock_magnitude_bps: int,
     *     duration_days: int,
     *     baseline_otif_bps: int,
     *     simulated_otif_bps: int,
     *     otif_delta_bps: int,
     *     at_risk_orders_count: int,
     *     estimated_margin_delta_idr: int,
     *     before_hash: string,
     *     after_hash: string,
     *     zero_mutation_verified: bool,
     *     simulation_run_id: string|int,
     *     simulation_fingerprint: string,
     *     tick_samples: list<array<string, mixed>>
     * }
     */
    public function runScenario(
        string $shockType,
        int $shockMagnitudeBps = 2500,
        int $durationDays = 14,
        ?string $name = null,
        ?int $actorUserId = null,
        int $seed = 53001,
        ?int $baselineOtifBps = null,
        ?int $activeOrderCount = null,
        int $dailyGrossMarginIdr = 25_000_000,
    ): array {
        $normalizedShock = strtoupper(trim($shockType));
        if (! in_array($normalizedShock, self::ALLOWED_SCENARIOS, true)) {
            throw new InvalidArgumentException("UNSUPPORTED_TWIN_SCENARIO: Skenario '{$shockType}' tidak didukung.");
        }
        if ($shockMagnitudeBps < 0 || $shockMagnitudeBps > 10_000) {
            throw new InvalidArgumentException('INVALID_TWIN_MAGNITUDE: Besaran kejutan harus 0..10000 bps.');
        }
        if ($durationDays < 1 || $durationDays > 365) {
            throw new InvalidArgumentException('INVALID_TWIN_DURATION: Durasi simulasi harus 1..365 hari.');
        }

        $scenarioName = $name !== null && trim($name) !== ''
            ? trim($name)
            : "What-If {$normalizedShock} ({$shockMagnitudeBps} bps / {$durationDays}d)";

        $latestSnapshotOtif = ControlTowerKpiSnapshot::query()->latest('id')->value('otif_bps');
        $resolvedBaselineOtif = max(0, min(10_000, $baselineOtifBps ?? (int) ($latestSnapshotOtif ?? 9600)));
        $promiseCount = OrderPromise::query()->count();
        $resolvedActiveOrders = max(1, $activeOrderCount ?? ($promiseCount > 0 ? $promiseCount : 100));

        $beforeHash = $this->computeOperationalStateHash();

        $runName = sprintf(
            'ct-twin-%s-%d-%d-%d',
            strtolower($normalizedShock),
            $shockMagnitudeBps,
            $durationDays,
            $seed,
        );
        $sandboxDays = min(14, $durationDays);
        $runId = $this->simulationRunner->start($runName, $sandboxDays, $seed, 'sandbox');
        $fingerprint = $this->simulationRunner->fingerprint($runId);

        $tickSamples = [];
        DB::beginTransaction();
        try {
            $virtualStart = $this->clock->now()->toImmutable();
            for ($day = 1; $day <= $sandboxDays; $day++) {
                $virtualDay = $virtualStart->addDays($day - 1);
                if ($this->tickHandler->shouldRun($virtualDay, $day)) {
                    $tickSamples[] = $this->tickHandler->handle($virtualDay, $day);
                }
            }
            DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $sim = $this->simulator->simulate(
            shockType: $normalizedShock,
            shockMagnitudeBps: $shockMagnitudeBps,
            durationDays: $durationDays,
            baselineOtifBps: $resolvedBaselineOtif,
            activeOrderCount: $resolvedActiveOrders,
            dailyGrossMarginIdr: $dailyGrossMarginIdr,
        );

        $afterHash = $this->computeOperationalStateHash();
        if ($beforeHash !== $afterHash) {
            throw new RuntimeException('SANDBOX_MUTATION_VIOLATION: Tabel operasional Control Tower bermutasi saat simulasi what-if.');
        }

        $nextSeq = (int) TwinScenario::query()->max('id') + 1;
        $code = 'TWN-'.strtoupper(substr(hash(
            'sha256',
            "{$scenarioName}|{$normalizedShock}|{$shockMagnitudeBps}|{$durationDays}|{$nextSeq}"
        ), 0, 8));

        $scenario = TwinScenario::create([
            'scenario_code' => $code,
            'name' => mb_substr($scenarioName, 0, 120),
            'shock_type' => $sim['shock_type'],
            'shock_magnitude_bps' => $shockMagnitudeBps,
            'duration_days' => $durationDays,
            'baseline_otif_bps' => $sim['baseline_otif_bps'],
            'simulated_otif_bps' => $sim['simulated_otif_bps'],
            'at_risk_orders_count' => $sim['at_risk_orders_count'],
            'estimated_margin_delta_idr' => $sim['estimated_margin_delta_idr'],
            'status' => 'completed',
            'created_by_user_id' => $actorUserId,
        ]);

        return [
            'scenario' => $scenario,
            'scenario_id' => (int) $scenario->id,
            'scenario_code' => (string) $scenario->scenario_code,
            'name' => (string) $scenario->name,
            'shock_type' => (string) $scenario->shock_type,
            'shock_magnitude_bps' => (int) $scenario->shock_magnitude_bps,
            'duration_days' => (int) $scenario->duration_days,
            'baseline_otif_bps' => (int) $scenario->baseline_otif_bps,
            'simulated_otif_bps' => (int) $scenario->simulated_otif_bps,
            'otif_delta_bps' => (int) $scenario->simulated_otif_bps - (int) $scenario->baseline_otif_bps,
            'at_risk_orders_count' => (int) $scenario->at_risk_orders_count,
            'estimated_margin_delta_idr' => (int) $scenario->estimated_margin_delta_idr,
            'before_hash' => $beforeHash,
            'after_hash' => $afterHash,
            'zero_mutation_verified' => true,
            'simulation_run_id' => $runId,
            'simulation_fingerprint' => $fingerprint,
            'tick_samples' => $tickSamples,
        ];
    }

    /**
     * Membandingkan 2 skenario what-if secara side-by-side.
     *
     * @return array{
     *     scenario_a: array<string, int|string>,
     *     scenario_b: array<string, int|string>,
     *     delta: array<string, int>,
     *     better_otif_scenario: string,
     *     lower_risk_scenario: string,
     *     better_margin_scenario: string
     * }
     */
    public function compareScenarios(int $idA, int $idB): array
    {
        $scenarioA = TwinScenario::query()->findOrFail($idA);
        $scenarioB = TwinScenario::query()->findOrFail($idB);

        $format = static fn (TwinScenario $s): array => [
            'id' => (int) $s->id,
            'scenario_code' => (string) $s->scenario_code,
            'name' => (string) $s->name,
            'shock_type' => (string) $s->shock_type,
            'shock_magnitude_bps' => (int) $s->shock_magnitude_bps,
            'duration_days' => (int) $s->duration_days,
            'baseline_otif_bps' => (int) $s->baseline_otif_bps,
            'simulated_otif_bps' => (int) $s->simulated_otif_bps,
            'at_risk_orders_count' => (int) $s->at_risk_orders_count,
            'estimated_margin_delta_idr' => (int) $s->estimated_margin_delta_idr,
            'status' => (string) $s->status,
        ];

        $otifA = (int) $scenarioA->simulated_otif_bps;
        $otifB = (int) $scenarioB->simulated_otif_bps;
        $riskA = (int) $scenarioA->at_risk_orders_count;
        $riskB = (int) $scenarioB->at_risk_orders_count;
        $marginA = (int) $scenarioA->estimated_margin_delta_idr;
        $marginB = (int) $scenarioB->estimated_margin_delta_idr;

        return [
            'scenario_a' => $format($scenarioA),
            'scenario_b' => $format($scenarioB),
            'delta' => [
                'simulated_otif_bps' => $otifB - $otifA,
                'at_risk_orders_count' => $riskB - $riskA,
                'estimated_margin_delta_idr' => $marginB - $marginA,
                'shock_magnitude_bps' => (int) $scenarioB->shock_magnitude_bps - (int) $scenarioA->shock_magnitude_bps,
                'duration_days' => (int) $scenarioB->duration_days - (int) $scenarioA->duration_days,
            ],
            'better_otif_scenario' => $otifA === $otifB
                ? 'TIE'
                : ($otifA > $otifB ? (string) $scenarioA->scenario_code : (string) $scenarioB->scenario_code),
            'lower_risk_scenario' => $riskA === $riskB
                ? 'TIE'
                : ($riskA < $riskB ? (string) $scenarioA->scenario_code : (string) $scenarioB->scenario_code),
            'better_margin_scenario' => $marginA === $marginB
                ? 'TIE'
                : ($marginA > $marginB ? (string) $scenarioA->scenario_code : (string) $scenarioB->scenario_code),
        ];
    }
}
