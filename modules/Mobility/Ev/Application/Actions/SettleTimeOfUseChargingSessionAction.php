<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\Actions;

use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSession;
use Modules\Shared\Application\BaseAction;

class SettleTimeOfUseChargingSessionAction extends BaseAction
{
    /**
     * Settle an active charging session with Time-of-Use (TOU) dynamic tariff.
     *
     * @return array{session: EvSession, total_cost_idr: int, net_payable_idr: int, refund_idr: int}
     */
    public function handle(
        int $sessionId,
        int $energyDeliveredWh,
        float $finalSohPct = 98.2,
        float $peakTempC = 34.5
    ): array {
        return $this->transaction(function () use ($sessionId, $energyDeliveredWh, $finalSohPct, $peakTempC) {
            $session = EvSession::where('id', $sessionId)->lockForUpdate()->firstOrFail();

            $kwh = max(0.1, $energyDeliveredWh / 1000.0);
            $tariffPerKwh = $session->tariff_per_kwh_idr ?: 2450;
            $totalCostIdr = (int) round($kwh * $tariffPerKwh);

            $deposit = $session->deposit_held_idr;
            $netPayableIdr = max(0, $totalCostIdr - $deposit);
            $refundIdr = max(0, $deposit - $totalCostIdr);

            $session->energy_wh = $energyDeliveredWh;
            $session->total_cost_idr = $totalCostIdr;
            $session->final_soh_pct = $finalSohPct;
            $session->peak_cell_temp_c = $peakTempC;
            $session->completed_at = now();
            $session->status = 'completed';
            $session->save();

            // Release charger
            if ($session->charger_id) {
                EvCharger::where('id', $session->charger_id)->update(['status' => 'available']);
            }

            $this->audit(
                action: 'ev.session.settled',
                auditable: $session,
                oldValues: ['status' => 'charging'],
                newValues: [
                    'status' => 'completed',
                    'total_cost_idr' => $totalCostIdr,
                    'energy_wh' => $energyDeliveredWh,
                    'net_payable_idr' => $netPayableIdr,
                ],
                correlationId: "ev_settle_{$session->session_code}",
                impactType: 'financial'
            );

            return [
                'session' => $session,
                'total_cost_idr' => $totalCostIdr,
                'net_payable_idr' => $netPayableIdr,
                'refund_idr' => $refundIdr,
            ];
        });
    }
}
