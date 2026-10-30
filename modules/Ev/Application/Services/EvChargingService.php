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
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;

class EvChargingService
{
    public const PEAK_TARIFF_IDR = 3000;

    public const OFF_PEAK_TARIFF_IDR = 2475;

    public const DEFAULT_TARIFF_IDR = 2500;

    public const NO_SHOW_FEE_IDR = 25000;

    public const DEPOSIT_HOLD_IDR = 50000;

    public function __construct(
        protected SimClockInterface $simClock,
        protected DigitalTwinInterface $digitalTwin,
        protected LedgerService $ledgerService,
        protected BatteryPassportService $batteryPassportService
    ) {}

    public function resolveTariff(Carbon $time): int
    {
        $hour = (int) $time->format('H');

        return ($hour >= 17 && $hour < 22) ? self::PEAK_TARIFF_IDR : self::DEFAULT_TARIFF_IDR;
    }

    public function reserveSlot(
        int $chargerId,
        int $vehicleId,
        int $userId,
        Carbon $startTime,
        int $durationMinutes = 30,
        ?int $explicitTariff = null
    ): EvSession {
        return DB::transaction(function () use ($chargerId, $vehicleId, $userId, $startTime, $durationMinutes, $explicitTariff) {
            $endTime = $startTime->copy()->addMinutes($durationMinutes);

            // Anti-overlap check
            $overlap = EvSession::where('charger_id', $chargerId)
                ->whereIn('status', ['reserved', 'plugged_in', 'charging'])
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

            $tariff = $explicitTariff ?? $this->resolveTariff($startTime);
            $sessionCode = 'EV-'.strtoupper(Str::random(8));

            return EvSession::create([
                'session_code' => $sessionCode,
                'charger_id' => $chargerId,
                'vehicle_id' => $vehicleId,
                'user_id' => $userId,
                'reserved_from' => $startTime,
                'reserved_to' => $endTime,
                'tariff_per_kwh_idr' => $tariff,
                'deposit_held_idr' => self::DEPOSIT_HOLD_IDR,
                'status' => 'reserved',
            ]);
        });
    }

    public function checkInQr(EvSession $session, string $qrCode): EvSession
    {
        return DB::transaction(function () use ($session, $qrCode) {
            $charger = $session->charger;
            if ($charger && $charger->charger_code !== $qrCode) {
                throw new \InvalidArgumentException('QR code does not match assigned charger.');
            }

            if ($session->reserved_to && $this->simClock->now()->isAfter($session->reserved_to)) {
                throw new \InvalidArgumentException('Reservation has expired.');
            }

            $session->status = 'plugged_in';
            $session->plugged_in_at = $this->simClock->now();
            $session->save();

            if ($charger) {
                $charger->status = 'charging';
                $charger->save();
            }

            return $session;
        });
    }

    public function completeCharging(
        EvSession $session,
        int $energyWh,
        ?float $sohAfterSession = null,
        float $peakCellTempC = 35.0
    ): EvSession {
        return DB::transaction(function () use ($session, $energyWh, $sohAfterSession, $peakCellTempC) {
            // 1. Calculate integer IDR billing
            $kwh = $energyWh / 1000.0;
            $totalCostIdr = (int) round($kwh * $session->tariff_per_kwh_idr);

            // 2. Resolve SoH and Battery Passport Block
            if ($sohAfterSession === null) {
                $deltaSoh = $this->batteryPassportService->calculateElectroDegradation($energyWh, $peakCellTempC);
                $finalSoh = (float) max(0.0, round((float) $session->initial_soh_pct - $deltaSoh, 2));
            } else {
                $finalSoh = $sohAfterSession;
            }

            // Record cryptographic block to oto_ev_battery_passports
            $this->batteryPassportService->recordSessionBlock($session, $energyWh, $peakCellTempC, $sohAfterSession);

            // Calculate ESG Scope 2 & Green Km
            $scope2Co2 = round($kwh * 0.794, 3);
            $greenKm = $kwh * 6.5;
            $iceCo2 = ($greenKm / 12.0) * 2.31;
            $greenKmSaved = round(max(0.0, $iceCo2 - $scope2Co2), 3);

            $session->energy_wh = $energyWh;
            $session->total_cost_idr = $totalCostIdr;
            $session->final_soh_pct = $finalSoh;
            $session->peak_cell_temp_c = $peakCellTempC;
            $session->scope2_co2_kg = $scope2Co2;
            $session->green_km_saved_co2_kg = $greenKmSaved;
            $session->completed_at = $this->simClock->now();
            $session->status = 'completed';
            $session->save();

            if ($session->charger) {
                $session->charger->status = 'available';
                $session->charger->save();
            }

            // 3. Hash-chained battery passport update via Digital Twin Bus
            $this->digitalTwin->recordState('vehicle_battery', (string) $session->vehicle_id, [
                'session_code' => $session->session_code,
                'energy_wh' => $energyWh,
                'soh_pct' => $finalSoh,
                'completed_at' => $session->completed_at->toIso8601String(),
            ]);

            // 4. Post to double-entry ledger: user wallet debit, oto:ev_revenue credit
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

    public function recordNoShow(EvSession $session, int $feeIdr = self::NO_SHOW_FEE_IDR): EvSession
    {
        return DB::transaction(function () use ($session, $feeIdr) {
            $session->status = 'no_show';
            $session->no_show_fee_idr = $feeIdr;
            $session->save();

            if ($session->charger) {
                $session->charger->status = 'available';
                $session->charger->save();
            }

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

    public function simulateGridLoadBalancing(int $stationId): array
    {
        $station = EvStation::with('chargers.sessions')->findOrFail($stationId);
        $activeChargers = $station->chargers->whereIn('status', ['charging', 'reserved']);
        $totalLoadKw = (int) $activeChargers->sum('max_kw');
        $capacityKw = (int) $station->transformer_capacity_kw;
        $utilizationPct = $capacityKw > 0 ? round(($totalLoadKw / $capacityKw) * 100.0, 1) : 0.0;

        $deferredSessions = 0;
        if ($utilizationPct > 80.0) {
            $pendingSessions = EvSession::whereIn('charger_id', $station->chargers->pluck('id'))
                ->where('status', 'reserved')
                ->get();

            foreach ($pendingSessions as $sess) {
                if ($sess->reserved_to) {
                    $sess->reserved_to = $sess->reserved_to->copy()->addMinutes(15);
                }
                $sess->deferred_minutes += 15;
                $sess->save();
                $deferredSessions++;
            }
        }

        return [
            'station_id' => $stationId,
            'transformer_capacity_kw' => $capacityKw,
            'total_load_kw' => $totalLoadKw,
            'utilization_pct' => $utilizationPct,
            'is_peak_overload' => $utilizationPct > 80.0,
            'deferred_sessions_count' => $deferredSessions,
        ];
    }

    /**
     * Two-source EV operations audit.
     * Cross-checks completed charging sessions against energy delivered, tariff billing,
     * and cryptographic Battery Passport chains.
     */
    public function auditEv(): array
    {
        $stationCount = EvStation::count();
        $chargerCount = EvCharger::count();
        $sessionCount = EvSession::count();
        $discrepancies = 0;

        // 1. Rekonsiliasi pendapatan sesi completed vs saldo ledger oto:ev_charging_revenue:IDR
        $totalCompletedRevenue = (int) EvSession::whereIn('status', ['COMPLETED', 'completed'])->sum('total_cost_idr');
        $ledgerRevenue = (int) $this->ledgerService->getCachedBalance('oto:ev_charging_revenue:IDR');
        if ($totalCompletedRevenue !== $ledgerRevenue) {
            $discrepancies++;
        }

        // 2. Verifikasi monotonisitas nilai meteran pengisian
        $meterSessions = DB::table('oto_ev_meter_values')->distinct()->pluck('session_id');
        foreach ($meterSessions as $sId) {
            $meters = DB::table('oto_ev_meter_values')
                ->where('session_id', $sId)
                ->orderBy('read_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            $prevWh = null;
            foreach ($meters as $m) {
                if ($prevWh !== null && $m->energy_wh < $prevWh) {
                    $discrepancies++;
                    break;
                }
                $prevWh = (int) $m->energy_wh;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'station_count' => $stationCount,
            'charger_count' => $chargerCount,
            'session_count' => $sessionCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
