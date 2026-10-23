<?php

declare(strict_types=1);

namespace Modules\Telematics\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Telematics\Domain\Models\TelematicsDevice;

class DispatchEmergencyBreakdownTowAction extends BaseAction
{
    /**
     * Dispatch emergency breakdown towing for a vehicle with critical failure.
     *
     * @return array{dispatch_id: string, vehicle_id: int, provider: string, fee_idr: int, status: string}
     */
    public function handle(
        int $vehicleId,
        string $locationText,
        string $reason,
        int $baseFeeIdr = 750000
    ): array {
        return $this->transaction(function () use ($vehicleId, $locationText, $reason, $baseFeeIdr) {
            $device = TelematicsDevice::where('vehicle_id', $vehicleId)->first();

            $dispatchId = 'TOW-'.str_pad((string) $vehicleId, 5, '0', STR_PAD_LEFT).'-'.date('Ymd');
            $idempotencyKey = hash('sha256', "tow:dispatch:{$vehicleId}:{$dispatchId}");

            // Ensure device is grounded if critical breakdown
            if ($device && $device->status !== 'grounded') {
                $device->status = 'grounded';
                $device->save();
            }

            $this->audit(
                action: 'telematics.tow.dispatched',
                auditable: $device,
                oldValues: null,
                newValues: [
                    'dispatch_id' => $dispatchId,
                    'vehicle_id' => $vehicleId,
                    'fee_idr' => $baseFeeIdr,
                    'status' => 'DISPATCHED',
                ],
                context: [
                    'location' => $locationText,
                    'reason' => $reason,
                ],
                correlationId: "tow_{$dispatchId}",
                impactType: 'emergency'
            );

            $this->outbox(
                eventType: 'EmergencyTowDispatched',
                payload: [
                    'dispatch_id' => $dispatchId,
                    'vehicle_id' => $vehicleId,
                    'fee_idr' => $baseFeeIdr,
                    'location' => $locationText,
                    'reason' => $reason,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'dispatch_id' => $dispatchId,
                'vehicle_id' => $vehicleId,
                'provider' => 'AutoServe Rapid Towing Network',
                'fee_idr' => $baseFeeIdr,
                'status' => 'DISPATCHED',
            ];
        });
    }
}
