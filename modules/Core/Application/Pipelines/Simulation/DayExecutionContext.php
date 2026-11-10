<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Models\SimRun;

/**
 * Konteks mutable berdurasi satu hari virtual dalam eksekusi simulasi pipeline.
 */
final class DayExecutionContext
{
    /** @var array<string, mixed> */
    public array $handlerOutputs = [];

    /** @var array<string, string> */
    public array $handlerHashes = [];

    public string $currentChainHash = '';

    public bool $isMilestoneDay = false;

    public int $totalEventsDispatched = 0;

    public float $dayExecutionTimeMs = 0.0;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly SimRun $run,
        public readonly int $dayNumber,
        public readonly CarbonInterface $virtualClockNow,
        public readonly string $previousChainHash,
        public array $metadata = [],
    ) {
        $this->currentChainHash = $previousChainHash;
    }

    public function recordHandlerOutput(string $handlerName, array $output): void
    {
        $this->handlerOutputs[$handlerName] = $output;

        ksort($output);
        $canonical = json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->handlerHashes[$handlerName] = hash('sha256', (string) $canonical);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDaySummary(): array
    {
        ksort($this->handlerHashes);

        return [
            'day' => $this->dayNumber,
            'handlers' => $this->handlerHashes,
            'events_count' => $this->totalEventsDispatched,
        ];
    }
}
