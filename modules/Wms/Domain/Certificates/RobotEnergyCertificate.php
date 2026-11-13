<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use InvalidArgumentException;

/**
 * Sertifikat deterministik estimasi konsumsi energi dan sisa baterai robot (integer persentase & mWh).
 */
final readonly class RobotEnergyCertificate
{
    /**
     * @param  list<array{mission_id:int, distance_m:int, payload_kg:int, estimated_drain_pct:int, energy_mwh:int, feasible:bool}>  $items
     */
    public function __construct(
        public int $robotId,
        public int $initialBatteryPct,
        public int $reserveMinBatteryPct,
        public int $totalDrainPct,
        public int $projectedEndBatteryPct,
        public int $totalEnergyMwh,
        public bool $allFeasible,
        public array $items,
        public string $signatureHash,
    ) {}

    public function verify(): void
    {
        if ($this->initialBatteryPct < 0 || $this->initialBatteryPct > 100) {
            throw new InvalidArgumentException('Initial battery_pct di luar rentang 0..100.');
        }

        $sumDrain = 0;
        $sumMwh = 0;
        $expectedFeasible = true;

        foreach ($this->items as $item) {
            $sumDrain += (int) $item['estimated_drain_pct'];
            $sumMwh += (int) $item['energy_mwh'];
            if (! (bool) $item['feasible']) {
                $expectedFeasible = false;
            }
        }

        if ($sumDrain !== $this->totalDrainPct) {
            throw new InvalidArgumentException("Sertifikat energi rusak: totalDrainPct ({$this->totalDrainPct}) != jumlah item ({$sumDrain}).");
        }

        if ($sumMwh !== $this->totalEnergyMwh) {
            throw new InvalidArgumentException("Sertifikat energi rusak: totalEnergyMwh ({$this->totalEnergyMwh}) != jumlah item ({$sumMwh}).");
        }

        $expectedEnd = max(0, $this->initialBatteryPct - $sumDrain);
        if ($expectedEnd !== $this->projectedEndBatteryPct) {
            throw new InvalidArgumentException("Sertifikat energi rusak: projectedEndBatteryPct ({$this->projectedEndBatteryPct}) != {$expectedEnd}.");
        }

        if ($expectedEnd < $this->reserveMinBatteryPct && $this->allFeasible) {
            throw new InvalidArgumentException('Sertifikat energi rusak: allFeasible=true padahal sisa baterai di bawah batas cadangan.');
        }

        if ($expectedFeasible !== $this->allFeasible) {
            throw new InvalidArgumentException('Sertifikat energi rusak: status allFeasible tidak konsisten dengan item.');
        }

        $expectedHash = self::computeHash(
            $this->robotId,
            $this->initialBatteryPct,
            $this->reserveMinBatteryPct,
            $this->totalDrainPct,
            $this->totalEnergyMwh,
            $this->items,
        );

        if (! hash_equals($expectedHash, $this->signatureHash)) {
            throw new InvalidArgumentException('Sertifikat energi rusak: signatureHash tidak valid.');
        }
    }

    /**
     * @param  list<array{mission_id:int, distance_m:int, payload_kg:int, estimated_drain_pct:int, energy_mwh:int, feasible:bool}>  $items
     */
    public static function computeHash(
        int $robotId,
        int $initialBatteryPct,
        int $reserveMinBatteryPct,
        int $totalDrainPct,
        int $totalEnergyMwh,
        array $items,
    ): string {
        $payload = json_encode([
            'robot_id' => $robotId,
            'initial_battery_pct' => $initialBatteryPct,
            'reserve_min_battery_pct' => $reserveMinBatteryPct,
            'total_drain_pct' => $totalDrainPct,
            'total_energy_mwh' => $totalEnergyMwh,
            'items' => $items,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
