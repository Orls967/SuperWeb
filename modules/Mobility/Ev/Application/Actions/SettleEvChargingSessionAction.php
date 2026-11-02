<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\Actions;

use Carbon\CarbonInterface;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Mobility\Ev\Application\Services\TimeOfUseTariffCalculator;
use Modules\Mobility\Ev\Domain\Events\EvSessionCompletedEvent;
use Modules\Mobility\Ev\Domain\Models\EvMeterValue;
use Modules\Mobility\Ev\Domain\Models\EvSession;
use Modules\Mobility\Ev\Domain\Models\EvTariffWindow;
use Modules\Mobility\Ev\Ledger\EvAccounts;
use Modules\Mobility\Ev\Ledger\EvTransactionTypes;

class SettleEvChargingSessionAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly TimeOfUseTariffCalculator $tariffCalculator
    ) {}

    public function execute(
        int $sessionId,
        CarbonInterface $completedAt,
        int $finalSohBps
    ): EvSession {
        $session = EvSession::with(['charger.station'])->findOrFail($sessionId);

        // Idempotensi: jika sudah selesai, kembalikan langsung
        if ($session->status === 'COMPLETED') {
            return $session;
        }

        // Ambil meter values urut waktu
        $meters = EvMeterValue::where('session_id', $sessionId)
            ->orderBy('read_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $station = $session->charger->station;
        $windows = EvTariffWindow::where('station_id', $station->id)->get()->map(function ($w) {
            return [
                'name' => $w->name,
                'start' => $w->start_time,
                'end' => $w->end_time,
                'rate' => (int) $w->rate_per_kwh_idr,
                'cross_midnight' => (bool) $w->is_cross_midnight,
            ];
        })->toArray();

        $totalCostIdr = 0;
        $totalEnergyWh = 0;

        if ($meters->count() >= 2) {
            for ($i = 0; $i < $meters->count() - 1; $i++) {
                $m1 = $meters[$i];
                $m2 = $meters[$i + 1];
                $deltaWh = $m2->energy_wh - $m1->energy_wh;
                if ($deltaWh <= 0) {
                    continue;
                }
                $totalEnergyWh += $deltaWh;

                if (! empty($windows)) {
                    $apportioned = $this->tariffCalculator->apportionEnergy(
                        fromTime: $m1->read_at,
                        toTime: $m2->read_at,
                        energyDeltaWh: $deltaWh,
                        windows: $windows
                    );

                    foreach ($windows as $win) {
                        $whInWindow = $apportioned[$win['name']] ?? 0;
                        if ($whInWindow > 0) {
                            $cost = (int) round(($whInWindow * $win['rate']) / 1000);
                            $totalCostIdr += $cost;
                        }
                    }
                }
            }
        } elseif ($meters->count() === 1) {
            $totalEnergyWh = $meters->first()->energy_wh;
        }

        $depositHeld = (int) $session->deposit_held_idr;
        $refundIdr = max(0, $depositHeld - $totalCostIdr);
        $userWallet = EvAccounts::ensureUserWallet($this->ledger, (int) $session->user_id);

        // 1. Capture pendapatan dari escrow
        if ($totalCostIdr > 0) {
            $lines = Posting::lines()
                ->debit(EvAccounts::ESCROW, $totalCostIdr)
                ->credit(EvAccounts::REVENUE, $totalCostIdr)
                ->toEntries('IDR');

            $this->ledger->post(new PostingDTO(
                type: EvTransactionTypes::EV_CAPTURE->value,
                description: "Capture Pendapatan EV {$session->session_code}",
                idempotencyKey: "oto:ev_capture:{$session->session_code}",
                entries: $lines,
                referenceType: 'EvSession',
                referenceId: (string) $session->id
            ));
        }

        // 2. Release sisa deposit ke dompet user
        if ($refundIdr > 0) {
            $lines = Posting::lines()
                ->debit(EvAccounts::ESCROW, $refundIdr)
                ->credit($userWallet, $refundIdr)
                ->toEntries('IDR');

            $this->ledger->post(new PostingDTO(
                type: EvTransactionTypes::EV_RELEASE->value,
                description: "Refund Deposit EV {$session->session_code}",
                idempotencyKey: "oto:ev_release:{$session->session_code}",
                entries: $lines,
                referenceType: 'EvSession',
                referenceId: (string) $session->id
            ));
        }

        // Update charger status kembali AVAILABLE
        $session->charger->update(['status' => 'AVAILABLE']);

        $session->update([
            'status' => 'COMPLETED',
            'completed_at' => $completedAt,
            'total_cost_idr' => $totalCostIdr,
            'refund_idr' => $refundIdr,
            'final_soh_bps' => $finalSohBps,
            'energy_wh' => $totalEnergyWh > 0 ? $totalEnergyWh : $session->energy_wh,
        ]);

        event(new EvSessionCompletedEvent(
            sessionId: $session->id,
            finalSohBps: $finalSohBps
        ));

        return $session->fresh();
    }
}
