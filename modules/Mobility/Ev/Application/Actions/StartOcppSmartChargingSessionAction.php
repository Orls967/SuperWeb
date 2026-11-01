<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\Actions;

use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSession;
use Modules\Shared\Application\BaseAction;

class StartOcppSmartChargingSessionAction extends BaseAction
{
    /**
     * Start an OCPP smart charging session.
     *
     * @return array{session: EvSession, session_code: string, deposit_held_idr: int, status: string}
     */
    public function handle(
        int $chargerId,
        int $vehicleId,
        int $userId,
        int $depositHeldIdr = 50000,
        int $tariffPerKwhIdr = 2450
    ): array {
        return $this->transaction(function () use ($chargerId, $vehicleId, $userId, $depositHeldIdr, $tariffPerKwhIdr) {
            $charger = EvCharger::where('id', $chargerId)->lockForUpdate()->firstOrFail();

            if ($charger->status === 'charging' || $charger->status === 'maintenance') {
                throw new \DomainException("EvCharger {$charger->charger_code} is currently {$charger->status} and cannot initiate session.");
            }

            $sessionCode = 'SES-EV-'.str_pad((string) $chargerId, 3, '0', STR_PAD_LEFT).'-'.date('YmdHis');
            $idempotencyKey = hash('sha256', "ev:ocpp:start:{$chargerId}:{$sessionCode}");

            $session = EvSession::create([
                'session_code' => $sessionCode,
                'charger_id' => $chargerId,
                'vehicle_id' => $vehicleId,
                'user_id' => $userId,
                'reserved_from' => now(),
                'reserved_to' => now()->addHours(2),
                'plugged_in_at' => now(),
                'energy_wh' => 0,
                'tariff_per_kwh_idr' => $tariffPerKwhIdr,
                'total_cost_idr' => 0,
                'deposit_held_idr' => $depositHeldIdr,
                'no_show_fee_idr' => 0,
                'initial_soh_pct' => 98.5,
                'peak_cell_temp_c' => 28.0,
                'deferred_minutes' => 0,
                'scope2_co2_kg' => 0.0,
                'green_km_saved_co2_kg' => 0.0,
                'status' => 'charging',
            ]);

            $charger->status = 'charging';
            $charger->save();

            $this->audit(
                action: 'ev.session.started',
                auditable: $session,
                oldValues: null,
                newValues: [
                    'session_code' => $sessionCode,
                    'charger_id' => $chargerId,
                    'deposit_held_idr' => $depositHeldIdr,
                    'status' => 'charging',
                ],
                correlationId: "ev_start_{$sessionCode}",
                impactType: 'energy'
            );

            $this->outbox(
                eventType: 'EvOcppSessionStarted',
                payload: [
                    'session_code' => $sessionCode,
                    'charger_id' => $chargerId,
                    'vehicle_id' => $vehicleId,
                    'deposit_held_idr' => $depositHeldIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'session' => $session,
                'session_code' => $sessionCode,
                'deposit_held_idr' => $depositHeldIdr,
                'status' => 'charging',
            ];
        });
    }
}
