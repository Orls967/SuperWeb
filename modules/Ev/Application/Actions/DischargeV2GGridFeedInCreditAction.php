<?php

declare(strict_types=1);

namespace Modules\Ev\Application\Actions;

use Modules\Ev\Domain\Models\EvStation;
use Modules\Shared\Application\BaseAction;

class DischargeV2GGridFeedInCreditAction extends BaseAction
{
    /**
     * Discharge EV battery into microgrid during peak demand and accrue credit to customer.
     *
     * @return array{station_id: int, vehicle_id: int, discharged_wh: int, credit_earned_idr: int, status: string}
     */
    public function handle(
        int $stationId,
        int $vehicleId,
        int $userId,
        int $dischargedWh,
        int $feedInRatePerKwhIdr = 2800
    ): array {
        return $this->transaction(function () use ($stationId, $vehicleId, $userId, $dischargedWh, $feedInRatePerKwhIdr) {
            $station = EvStation::where('id', $stationId)->firstOrFail();

            $kwhDischarged = max(0.1, $dischargedWh / 1000.0);
            $creditEarnedIdr = (int) round($kwhDischarged * $feedInRatePerKwhIdr);

            $idempotencyKey = hash('sha256', "v2g:feedin:{$stationId}:{$vehicleId}:{$dischargedWh}");

            $this->audit(
                action: 'ev.v2g.credit_earned',
                auditable: $station,
                oldValues: null,
                newValues: [
                    'station_id' => $stationId,
                    'vehicle_id' => $vehicleId,
                    'user_id' => $userId,
                    'discharged_wh' => $dischargedWh,
                    'credit_earned_idr' => $creditEarnedIdr,
                ],
                correlationId: "v2g_{$stationId}_{$vehicleId}",
                impactType: 'energy_credit'
            );

            $this->outbox(
                eventType: 'EvV2GDischargeCompleted',
                payload: [
                    'station_id' => $stationId,
                    'vehicle_id' => $vehicleId,
                    'user_id' => $userId,
                    'discharged_wh' => $dischargedWh,
                    'credit_earned_idr' => $creditEarnedIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'station_id' => $stationId,
                'vehicle_id' => $vehicleId,
                'discharged_wh' => $dischargedWh,
                'credit_earned_idr' => $creditEarnedIdr,
                'status' => 'ACCRUED',
            ];
        });
    }
}
