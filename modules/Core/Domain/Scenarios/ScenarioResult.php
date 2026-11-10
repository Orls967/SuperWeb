<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Scenarios;

/**
 * Hasil eksekusi sebuah skenario simulasi bisnis.
 */
final readonly class ScenarioResult
{
    /**
     * @param  array<string, mixed>  $kpis
     * @param  array<int, string>  $alerts
     */
    public function __construct(
        public string $scenarioCode,
        public string $runId,
        public bool $success,
        public int $executedDays,
        public string $finalHash,
        public array $kpis = [],
        public array $alerts = [],
        public float $executionTimeSeconds = 0.0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_code' => $this->scenarioCode,
            'run_id' => $this->runId,
            'success' => $this->success,
            'executed_days' => $this->executedDays,
            'final_hash' => $this->finalHash,
            'kpis' => $this->kpis,
            'alerts' => $this->alerts,
            'execution_time_seconds' => $this->executionTimeSeconds,
        ];
    }
}
