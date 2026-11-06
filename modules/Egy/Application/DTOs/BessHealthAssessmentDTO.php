<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Laporan Penilaian Kesehatan dan Degradasi Sel Baterai BESS.
 */
final class BessHealthAssessmentDTO
{
    /**
     * @param  array<string, int>  $degradationBreakdownBps
     */
    public function __construct(
        public readonly string $bessCode,
        public readonly string $batteryChemistry,
        public readonly int $nominalCapacityKwh,
        public readonly int $currentRetainedCapacityKwh,
        public readonly int $stateOfHealthBps,
        public readonly int $totalEquivalentFullCycles,
        public readonly int $calendarAgingLossBps,
        public readonly int $cyclingAgingLossBps,
        public readonly int $estimatedRemainingCyclesTo80Pct,
        public readonly int $cumulativeReplacementReserveAccruedIdr,
        public readonly array $degradationBreakdownBps,
        public readonly string $assessmentHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bess_code' => $this->bessCode,
            'battery_chemistry' => $this->batteryChemistry,
            'nominal_capacity_kwh' => $this->nominalCapacityKwh,
            'current_retained_capacity_kwh' => $this->currentRetainedCapacityKwh,
            'state_of_health_bps' => $this->stateOfHealthBps,
            'total_equivalent_full_cycles' => $this->totalEquivalentFullCycles,
            'calendar_aging_loss_bps' => $this->calendarAgingLossBps,
            'cycling_aging_loss_bps' => $this->cyclingAgingLossBps,
            'estimated_remaining_cycles' => $this->estimatedRemainingCyclesTo80Pct,
            'cumulative_replacement_reserve_idr' => $this->cumulativeReplacementReserveAccruedIdr,
            'degradation_breakdown' => $this->degradationBreakdownBps,
            'assessment_hash' => $this->assessmentHash,
        ];
    }
}
