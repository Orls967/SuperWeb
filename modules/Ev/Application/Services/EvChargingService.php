<?php

declare(strict_types=1);

namespace Modules\Ev\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Ev\Domain\Models\EvSession;

class EvChargingService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected DigitalTwinInterface $digitalTwin,
        protected LedgerService $ledgerService
    ) {}

    public function reserveSlot(
        int $chargerId,
        int $vehicleId,
        int $userId,
        Carbon $startTime,
        int $durationMinutes = 30
    ): EvSession {
        return DB::transaction(function () use ($chargerId, $vehicleId, $userId, $startTime, $durationMinutes) {
            $endTime = $startTime->copy()->addMinutes($durationMinutes);

            // Anti-overlap check
            $overlap = EvSession::where('charger_id', $chargerId)
                ->whereIn('status', ['reserved', 'charging'])
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->whereBetween('reserved_from', [$startTime, $endTime])
                        ->orWhereBetween('reserved_to', [$startTime, $endTime])
                        ->orWhere(function ($q) use ($startTime, $endTime) {
                            $q->where('reserved_from', '<=', $startTime)
                                ->where('reserved_to', '>=', $endTime);
                        });
                })
                ->exists();

            if ($overlap) {
                throw new \InvalidArgumentException('Charger slot is already reserved for the selected timeframe.');
            }

            return EvSession::create([
                'session_code' => 'EV-'.strtoupper(Str::random(8)),
                'charger_id' => $chargerId,
                'vehicle_id' => $vehicleId,
                'user_id' => $userId,
                'reserved_from' => $startTime,
                'reserved_to' => $endTime,
                'tariff_per_kwh_idr' => 2500,
                'status' => 'reserved',
            ]);
        });
    }

    public function completeCharging(
        EvSession $session,
        int $energyWh,
        float $sohAfterSession
    ): EvSession {
        return DB::transaction(function () use ($session, $energyWh, $sohAfterSession) {
            // 1. Calculate integer IDR billing
            $kwh = $energyWh / 1000.0;
            $totalCostIdr = (int) round($kwh * $session->tariff_per_kwh_idr);

            $session->energy_wh = $energyWh;
            $session->total_cost_idr = $totalCostIdr;
            $session->final_soh_pct = $sohAfterSession;
            $session->completed_at = $this->simClock->now();
            $session->status = 'completed';
            $session->save();

            // 2. Hash-chained battery passport update via Digital Twin Bus
            $this->digitalTwin->recordState('vehicle_battery', (string) $session->vehicle_id, [
                'session_code' => $session->session_code,
                'energy_wh' => $energyWh,
                'soh_pct' => $sohAfterSession,
                'completed_at' => $session->completed_at->toIso8601String(),
            ]);

            // 3. Post to double-entry ledger: user wallet debit, oto:ev_revenue credit
            $this->ledgerService->post(new PostingDTO(
                type: 'payment',
                description: 'EV Charging Payment',
                idempotencyKey: "ev:bill:{$session->id}",
                entries: [
                    PostingEntryDTO::forCode("wallet:user:{$session->user_id}:IDR", 'IDR', -$totalCostIdr),
                    PostingEntryDTO::forCode('oto:ev_revenue:IDR', 'IDR', $totalCostIdr),
                ],
                referenceType: 'ev_session',
                referenceId: (string) $session->id,
            ));

            return $session;
        });
    }

    public function recordNoShow(EvSession $session, int $feeIdr = 25000): EvSession
    {
        return DB::transaction(function () use ($session, $feeIdr) {
            $session->status = 'no_show';
            $session->no_show_fee_idr = $feeIdr;
            $session->save();

            $this->ledgerService->post(new PostingDTO(
                type: 'fee',
                description: 'EV Charging No Show Fee',
                idempotencyKey: "ev:noshow:{$session->id}",
                entries: [
                    PostingEntryDTO::forCode("wallet:user:{$session->user_id}:IDR", 'IDR', -$feeIdr),
                    PostingEntryDTO::forCode('oto:ev_revenue:IDR', 'IDR', $feeIdr),
                ],
                referenceType: 'ev_session',
                referenceId: (string) $session->id,
            ));

            return $session;
        });
    }
}
