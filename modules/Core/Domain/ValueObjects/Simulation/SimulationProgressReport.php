<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use InvalidArgumentException;

/**
 * Value Object ringkasan progres jalannya sesi simulasi.
 */
final readonly class SimulationProgressReport
{
    public function __construct(
        public string $runId,
        public int $currentDay,
        public int $totalDays,
        public string $currentChainHash,
        public float $elapsedSeconds,
        public bool $isCompleted,
    ) {
        if ($this->totalDays <= 0) {
            throw new InvalidArgumentException("Total hari harus > 0: {$this->totalDays}");
        }
        if ($this->currentDay < 0 || $this->currentDay > $this->totalDays) {
            throw new InvalidArgumentException("Hari saat ini ({$this->currentDay}) di luar rentang total ({$this->totalDays}).");
        }
    }

    public function progressPercentage(): float
    {
        return round(($this->currentDay / $this->totalDays) * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'current_day' => $this->currentDay,
            'total_days' => $this->totalDays,
            'progress_pct' => $this->progressPercentage(),
            'current_chain_hash' => $this->currentChainHash,
            'elapsed_seconds' => round($this->elapsedSeconds, 3),
            'is_completed' => $this->isCompleted,
        ];
    }
}
