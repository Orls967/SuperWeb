<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Application\Actions;

use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Modules\Shared\Application\BaseAction;

class EvaluateCriticalDtcAndGroundVehicleAction extends BaseAction
{
    public const CRITICAL_DTC_CODES = [
        'P0010', // Camshaft Position Actuator Circuit (Bank 1)
        'P0087', // Fuel Rail/System Pressure - Too Low
        'P0117', // Engine Coolant Temp Circuit Low
        'P0217', // Engine Overtemp Condition
        'P0300', // Random/Multiple Cylinder Misfire Detected
        'P0562', // System Voltage Low
        'P0606', // ECM / PCM Processor Fault
    ];

    /**
     * Evaluate diagnostic trouble code and ground vehicle device if critical.
     *
     * @return array{grounded: bool, severity: string, device: ?TelematicsDevice, dtc_code: ?string}
     */
    public function handle(string $deviceId, ?string $dtcCode): array
    {
        return $this->transaction(function () use ($deviceId, $dtcCode) {
            $device = TelematicsDevice::where('device_id', $deviceId)->first();
            $code = $dtcCode ? strtoupper(trim($dtcCode)) : null;

            $isCritical = $code !== null && in_array($code, self::CRITICAL_DTC_CODES, true);
            $severity = $isCritical ? 'critical' : ($code ? 'advisory' : 'normal');

            if ($device && $isCritical) {
                $oldStatus = $device->status;
                $device->status = 'grounded';
                $device->save();

                $this->audit(
                    action: 'telematics.vehicle.grounded',
                    auditable: $device,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'grounded', 'dtc_code' => $code],
                    context: ['dtc_code' => $code, 'severity' => 'critical'],
                    correlationId: "tel_ground_{$deviceId}_{$code}",
                    impactType: 'safety'
                );

                $this->outbox(
                    eventType: 'TelematicsVehicleGrounded',
                    payload: [
                        'device_id' => $deviceId,
                        'vehicle_id' => $device->vehicle_id,
                        'dtc_code' => $code,
                        'grounded_at' => now()->toIso8601String(),
                    ],
                    idempotencyKey: hash('sha256', "ground:{$deviceId}:{$code}")
                );
            }

            return [
                'grounded' => $isCritical,
                'severity' => $severity,
                'device' => $device,
                'dtc_code' => $code,
            ];
        });
    }
}
