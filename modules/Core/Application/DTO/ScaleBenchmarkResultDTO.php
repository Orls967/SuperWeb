<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTO;

final readonly class ScaleBenchmarkResultDTO
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $tier,
        public int $targetDays,
        public float $elapsedSeconds,
        public float $daysPerSecond,
        public int $peakMemoryBytes,
        public array $parameters = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier,
            'target_days' => $this->targetDays,
            'elapsed_seconds' => round($this->elapsedSeconds, 4),
            'days_per_second' => round($this->daysPerSecond, 2),
            'peak_memory_mb' => round($this->peakMemoryBytes / (1024 * 1024), 2),
            'parameters' => $this->parameters,
        ];
    }
}
