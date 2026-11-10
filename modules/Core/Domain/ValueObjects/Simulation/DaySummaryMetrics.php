<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use InvalidArgumentException;

/**
 * Value Object ringkasan metrik eksekusi harian kernel simulasi.
 */
final readonly class DaySummaryMetrics
{
    /**
     * @param  array<string, int>  $handlerExecutionCounts
     * @param  array<string, float>  $handlerExecutionTimesMs
     * @param  array<string, string>  $handlerStateHashes
     */
    public function __construct(
        public int $dayNumber,
        public int $totalEventsDispatched,
        public int $totalTicksExecuted,
        public float $elapsedRealTimeSeconds,
        public array $handlerExecutionCounts = [],
        public array $handlerExecutionTimesMs = [],
        public array $handlerStateHashes = [],
    ) {
        if ($this->dayNumber < 1) {
            throw new InvalidArgumentException("Hari harus >= 1: {$this->dayNumber}");
        }
        if ($this->totalEventsDispatched < 0 || $this->totalTicksExecuted < 0) {
            throw new InvalidArgumentException('Jumlah event dan tick tidak boleh negatif.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dayNumber: (int) ($data['day_number'] ?? 1),
            totalEventsDispatched: (int) ($data['total_events_dispatched'] ?? 0),
            totalTicksExecuted: (int) ($data['total_ticks_executed'] ?? 0),
            elapsedRealTimeSeconds: (float) ($data['elapsed_real_time_seconds'] ?? 0.0),
            handlerExecutionCounts: (array) ($data['handler_execution_counts'] ?? []),
            handlerExecutionTimesMs: (array) ($data['handler_execution_times_ms'] ?? []),
            handlerStateHashes: (array) ($data['handler_state_hashes'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'day_number' => $this->dayNumber,
            'total_events_dispatched' => $this->totalEventsDispatched,
            'total_ticks_executed' => $this->totalTicksExecuted,
            'elapsed_real_time_seconds' => $this->elapsedRealTimeSeconds,
            'handler_execution_counts' => $this->handlerExecutionCounts,
            'handler_execution_times_ms' => $this->handlerExecutionTimesMs,
            'handler_state_hashes' => $this->handlerStateHashes,
        ];
    }
}
