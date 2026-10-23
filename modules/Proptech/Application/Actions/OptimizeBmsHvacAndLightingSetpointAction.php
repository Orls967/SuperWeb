<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\HvacCommand;
use Modules\Shared\Application\BaseAction;

class OptimizeBmsHvacAndLightingSetpointAction extends BaseAction
{
    /**
     * Algorithmic BMS HVAC & Lighting setpoint optimization.
     *
     * @return array{zone_id: int, target_temp_c: float, lighting_mode: string, estimated_kwh_saved: float, savings_idr: int, hvac_command: HvacCommand}
     */
    public function handle(
        int $zoneId,
        float $ambientTempC,
        int $currentOccupancy
    ): array {
        return DB::transaction(function () use ($zoneId, $ambientTempC, $currentOccupancy) {
            $zone = BuildingZone::lockForUpdate()->findOrFail($zoneId);

            [$targetTempC, $lightingMode, $commandType, $reason, $kwhSaved] = match (true) {
                $currentOccupancy === 0 => [26.5, 'OFF', 'SETBACK', 'Zero occupancy setback schedule', 4.5],
                $currentOccupancy > 20 => [22.0, 'HIGH_EFFICIENCY_100', 'SET_TEMP', 'High density occupancy cooling surge', 1.2],
                $ambientTempC > 32.0 => [23.5, 'ADAPTIVE_DIM_70', 'SET_TEMP', 'High outdoor ambient temperature mitigation', 2.0],
                default => [24.0, 'ADAPTIVE_DIM_80', 'SET_TEMP', 'Normal baseline comfort mode', 2.8],
            };

            $zone->current_temp_c = $targetTempC;
            $zone->current_occupancy = $currentOccupancy;
            $zone->save();

            $command = HvacCommand::create([
                'zone_id' => $zone->id,
                'command_type' => $commandType,
                'target_value' => $targetTempC,
                'trigger_reason' => $reason,
                'estimated_kwh_saved' => $kwhSaved,
            ]);

            $kwhRate = (int) ($zone->current_kwh_rate ?: 1500);
            $savingsIdr = (int) round($kwhSaved * $kwhRate);

            $this->audit(
                action: 'proptech.bms.optimized',
                auditable: $command,
                oldValues: ['temp_c' => $ambientTempC],
                newValues: [
                    'zone_id' => $zone->id,
                    'target_temp_c' => $targetTempC,
                    'lighting_mode' => $lightingMode,
                    'estimated_kwh_saved' => $kwhSaved,
                    'savings_idr' => $savingsIdr,
                ],
                correlationId: "prp_bms_{$zone->id}_".date('YmdHis'),
                impactType: 'bms_optimization'
            );

            return [
                'zone_id' => $zone->id,
                'target_temp_c' => $targetTempC,
                'lighting_mode' => $lightingMode,
                'estimated_kwh_saved' => $kwhSaved,
                'savings_idr' => $savingsIdr,
                'hvac_command' => $command,
            ];
        });
    }
}
