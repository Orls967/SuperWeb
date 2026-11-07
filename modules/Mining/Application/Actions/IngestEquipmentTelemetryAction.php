<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Application\Sagas\PredictiveMaintenanceWorkOrderSaga;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningEquipmentTelemetry;

/**
 * Aksi Ingest Telemetri IoT Alat Berat Idempoten & Pemicu Perawatan Prediktif (D93-3, D93-4).
 */
class IngestEquipmentTelemetryAction
{
    public function __construct(
        private readonly PredictiveMaintenanceWorkOrderSaga $maintenanceSaga,
        private readonly ClockInterface $clock
    ) {}

    /**
     * @param  array{
     *     equipment_id: string,
     *     sequence_number: int,
     *     engine_hours_total: int,
     *     vibration_rms_scaled: int,
     *     fuel_level_percent?: int,
     *     instant_fuel_rate_ml_s?: int,
     *     payload_kg?: int,
     *     latitude_scaled?: int,
     *     longitude_scaled?: int
     * }  $params
     * @return array{telemetry_id: string, is_duplicate: bool, maintenance_triggered: bool}
     */
    public function execute(array $params, ?User $actor = null): array
    {
        return DB::transaction(function () use ($params, $actor) {
            $equipment = MiningEquipment::findOrFail($params['equipment_id']);

            // Idempotency check pada (equipment_id, sequence_number)
            $existing = MiningEquipmentTelemetry::where('equipment_id', $equipment->id)
                ->where('sequence_number', $params['sequence_number'])
                ->first();

            if ($existing) {
                return [
                    'telemetry_id' => $existing->id,
                    'is_duplicate' => true,
                    'maintenance_triggered' => false,
                ];
            }

            $telemetry = MiningEquipmentTelemetry::create([
                'equipment_id' => $equipment->id,
                'sequence_number' => $params['sequence_number'],
                'recorded_at' => $this->clock->now(),
                'engine_hours_total' => $params['engine_hours_total'],
                'fuel_level_percent' => $params['fuel_level_percent'] ?? 100,
                'instant_fuel_rate_ml_s' => $params['instant_fuel_rate_ml_s'] ?? 0,
                'payload_kg' => $params['payload_kg'] ?? 0,
                'vibration_rms_scaled' => $params['vibration_rms_scaled'],
                'latitude_scaled' => $params['latitude_scaled'] ?? 0,
                'longitude_scaled' => $params['longitude_scaled'] ?? 0,
            ]);

            // Update jam operasional unit
            if ($params['engine_hours_total'] > $equipment->engine_hours) {
                $equipment->update(['engine_hours' => $params['engine_hours_total']]);
            }

            // Ambang batas pemeliharaan prediktif: jam mesin > 5000 jam ATAU vibrasi > 1800 rms
            $maintenanceTriggered = false;
            if ($params['engine_hours_total'] > 5000 || $params['vibration_rms_scaled'] > 1800) {
                $maintenanceTriggered = true;

                $shiftDate = $this->clock->now()->toDateString();
                $shiftCode = 'SHIFT_A';

                $this->maintenanceSaga->execute([
                    'equipment_id' => (string) $equipment->id,
                    'site_id' => (string) $equipment->site_id,
                    'shift_date' => $shiftDate,
                    'shift_code' => $shiftCode,
                    'reason_code' => 'PREDICTIVE_VIBRATION_HOURS_EXCEEDED',
                    'description' => "Pemeliharaan prediktif otomatis unit {$equipment->equipment_code} (Jam: {$params['engine_hours_total']}, Getaran: {$params['vibration_rms_scaled']})",
                    'downtime_hours' => 4,
                    'parts_list' => [
                        [
                            'material_code' => 'SP-BEARING-777D',
                            'qty' => 2.0,
                            'unit' => 'PCS',
                            'lead_time_days' => 3,
                            'moq' => 1.0,
                            'estimated_unit_price_idr' => 25000000,
                            'note' => 'Penggantian bearing dan filter oli unit haul truck',
                        ],
                    ],
                ], $actor ?? User::first() ?? new User);
            }

            return [
                'telemetry_id' => $telemetry->id,
                'is_duplicate' => false,
                'maintenance_triggered' => $maintenanceTriggered,
            ];
        });
    }
}
