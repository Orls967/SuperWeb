<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Contracts\SimulationRunner;
use Modules\Core\Domain\Scenarios\ScenarioResult;
use Modules\Core\Domain\Scenarios\SimulationScenario;

/**
 * Layanan orkestrasi dan eksekusi skenario simulasi tingkat tinggi.
 */
final class ScenarioRunnerService
{
    /** @var array<string, SimulationScenario> */
    private array $catalog = [];

    public function __construct(
        private readonly SimulationRunner $runner,
    ) {
        $this->registerBuiltinScenarios();
    }

    private function registerBuiltinScenarios(): void
    {
        $this->catalog['hospital_surge'] = new SimulationScenario(
            code: 'hospital_surge',
            title: 'Lonjakan Pasien Pasca Bencana',
            description: 'Simulasi stres kapasitas tempat tidur rawat inap dan percepatan siklus discharge',
            durationDays: 30,
            defaultSeed: 1001,
            parameters: ['patient_arrival_multiplier' => 2.5, 'discharge_rush' => true],
        );

        $this->catalog['hotel_holiday_peak'] = new SimulationScenario(
            code: 'hotel_holiday_peak',
            title: 'Puncak Musim Liburan Hotel & Resort',
            description: 'Okupansi 100% dengan lonjakan transaksi night audit, room service, dan surcharge',
            durationDays: 14,
            defaultSeed: 2002,
            parameters: ['occupancy_rate' => 0.98, 'overbooking_buffer' => 0.05],
        );

        $this->catalog['port_congestion_demurrage'] = new SimulationScenario(
            code: 'port_congestion_demurrage',
            title: 'Kemacetan Pelabuhan Logistik & Lonjakan Demurrage',
            description: 'Penumpukan peti kemas melampaui free-time memicu penalti berjenjang',
            durationDays: 45,
            defaultSeed: 3003,
            parameters: ['delay_days_average' => 8, 'penalty_escalation' => true],
        );
    }

    public function registerScenario(SimulationScenario $scenario): void
    {
        $this->catalog[$scenario->code] = $scenario;
    }

    /**
     * @return array<string, SimulationScenario>
     */
    public function getCatalog(): array
    {
        return $this->catalog;
    }

    public function runScenario(string $code, string $mode = 'sandbox'): ScenarioResult
    {
        if (! isset($this->catalog[$code])) {
            throw new \InvalidArgumentException("Skenario simulasi [{$code}] tidak ditemukan.");
        }

        $scenario = $this->catalog[$code];
        $startSec = microtime(true);

        $runName = "scenario-{$scenario->code}-".uniqid();
        $runId = $this->runner->start(
            name: $runName,
            days: $scenario->durationDays,
            seed: $scenario->defaultSeed,
            mode: $mode
        );

        $finalFingerprint = $this->runner->fingerprint($runId);
        $duration = microtime(true) - $startSec;

        return new ScenarioResult(
            scenarioCode: $scenario->code,
            runId: $runId,
            success: true,
            executedDays: $scenario->durationDays,
            finalHash: $finalFingerprint,
            kpis: [
                'mode' => $mode,
                'seed' => $scenario->defaultSeed,
                'real_execution_seconds' => round($duration, 3),
            ],
            alerts: [],
            executionTimeSeconds: $duration,
        );
    }
}
