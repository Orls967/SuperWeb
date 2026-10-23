<?php

declare(strict_types=1);

namespace Modules\Ev\Application\Actions;

use Modules\Ev\Domain\Models\EvBatteryPassport;
use Modules\Shared\Application\BaseAction;

class AppendBatteryPassportLifecycleEventAction extends BaseAction
{
    public const GENESIS_PREFIX = 'GENESIS_BATTERY_';

    /**
     * Append an immutable, cryptographic hash chain event to the EV Battery Passport.
     */
    public function handle(
        int $vehicleId,
        string $sessionCode,
        int $energyWh,
        float $initialSoh = 99.0,
        float $finalSoh = 98.7,
        float $maxCellTempC = 33.2
    ): EvBatteryPassport {
        return $this->transaction(function () use (
            $vehicleId,
            $sessionCode,
            $energyWh,
            $initialSoh,
            $finalSoh,
            $maxCellTempC
        ) {
            $latest = EvBatteryPassport::where('vehicle_id', $vehicleId)
                ->orderByDesc('cycle_number')
                ->first();

            $prevHash = $latest ? $latest->block_hash : hash('sha256', self::GENESIS_PREFIX.$vehicleId);
            $cycleNumber = $latest ? ($latest->cycle_number + 1) : 1;
            $deltaSoh = round($initialSoh - $finalSoh, 4);
            $recordedAt = now();

            $payload = implode('|', [
                $prevHash,
                $vehicleId,
                $sessionCode,
                $cycleNumber,
                $energyWh,
                number_format($finalSoh, 2, '.', ''),
                number_format($maxCellTempC, 2, '.', ''),
                $recordedAt->toIso8601String(),
            ]);

            $blockHash = hash('sha256', $payload);

            $block = EvBatteryPassport::create([
                'vehicle_id' => $vehicleId,
                'session_code' => $sessionCode,
                'cycle_number' => $cycleNumber,
                'initial_soh_pct' => $initialSoh,
                'final_soh_pct' => $finalSoh,
                'delta_soh' => $deltaSoh,
                'max_cell_temp_c' => $maxCellTempC,
                'energy_wh' => $energyWh,
                'prev_hash' => $prevHash,
                'block_hash' => $blockHash,
                'recorded_at' => $recordedAt,
            ]);

            $this->audit(
                action: 'ev.battery.block_appended',
                auditable: $block,
                oldValues: null,
                newValues: [
                    'vehicle_id' => $vehicleId,
                    'cycle_number' => $cycleNumber,
                    'block_hash' => $blockHash,
                    'final_soh_pct' => $finalSoh,
                ],
                correlationId: "batt_blk_{$vehicleId}_{$cycleNumber}",
                impactType: 'battery_passport'
            );

            $this->outbox(
                eventType: 'BatteryPassportBlockAppended',
                payload: [
                    'vehicle_id' => $vehicleId,
                    'cycle_number' => $cycleNumber,
                    'session_code' => $sessionCode,
                    'block_hash' => $blockHash,
                    'final_soh_pct' => $finalSoh,
                ],
                idempotencyKey: hash('sha256', "batt:cycle:{$vehicleId}:{$cycleNumber}")
            );

            return $block;
        });
    }
}
