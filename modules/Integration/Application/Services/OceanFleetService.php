<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OceanFleetService (Fase 180 — Lini 27)
 *
 * Implements:
 *  - 180.1 Vessel asset register with digital vessel passport hash
 *  - 180.2 Voyage planning with deadweight tonnage (DWT) capacity enforcement
 *  - 180.1 Safety certificate expiry gating (overdue certificate blocks voyage dispatch)
 *  - 180.2 Charter party laytime & demurrage settlement calculation
 */
class OceanFleetService
{
    /**
     * Register vessel with immutable passport hash.
     */
    public function registerVessel(string $imoNumber, string $name, string $vesselClass, float $dwt, Carbon $certExpiry): object
    {
        $hash = hash('sha256', "IMO:{$imoNumber}:{$name}:{$vesselClass}:{$dwt}");

        DB::table('flt_vessels')->updateOrInsert(
            ['imo_number' => $imoNumber],
            [
                'vessel_name' => $name,
                'vessel_class' => strtoupper($vesselClass),
                'deadweight_tonnage_dwt' => $dwt,
                'safety_certificate_expiry' => $certExpiry->toDateString(),
                'is_detained' => false,
                'passport_hash' => $hash,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('flt_vessels')->where('imo_number', $imoNumber)->first();
    }

    /**
     * Plan voyage. Enforces DWT cargo capacity and safety certificate validity.
     */
    public function planVoyage(string $imoNumber, string $originPort, string $destPort, float $cargoTons, float $bunkerFuelTons): object
    {
        $vessel = DB::table('flt_vessels')->where('imo_number', $imoNumber)->first();
        if (! $vessel) {
            throw new \InvalidArgumentException("Vessel IMO {$imoNumber} not found.");
        }

        // Safety certificate expiry check
        if (Carbon::parse($vessel->safety_certificate_expiry)->isPast() || (bool) $vessel->is_detained) {
            throw new \RuntimeException("Voyage dispatch blocked: Vessel IMO {$imoNumber} safety/class certificate is expired or vessel is detained.");
        }

        // Deadweight capacity check
        $maxDwt = (float) $vessel->deadweight_tonnage_dwt;
        if ($cargoTons + $bunkerFuelTons > $maxDwt) {
            throw new \RuntimeException("Capacity exceeded: Combined cargo and bunker load ({$cargoTons} + {$bunkerFuelTons} tons) exceeds vessel DWT ({$maxDwt} tons).");
        }

        $code = 'VOY-FLT-'.strtoupper(Str::random(8));

        $id = DB::table('flt_voyages')->insertGetId([
            'voyage_code' => $code,
            'imo_number' => $imoNumber,
            'origin_port' => strtoupper($originPort),
            'destination_port' => strtoupper($destPort),
            'planned_cargo_tons' => $cargoTons,
            'bunker_fuel_metric_tons' => $bunkerFuelTons,
            'status' => 'SCHEDULED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('flt_voyages')->find($id);
    }

    /**
     * Settle charter party agreement with demurrage calculation.
     */
    public function settleCharter(string $voyageCode, float $dailyHireUsd, float $days, float $demurrageUsd = 0.0): object
    {
        $hireTotal = round($dailyHireUsd * $days, 2);
        $grandTotal = round($hireTotal + $demurrageUsd, 2);

        $code = 'CHT-FLT-'.strtoupper(Str::random(8));

        $id = DB::table('flt_charter_settlements')->insertGetId([
            'charter_code' => $code,
            'voyage_code' => $voyageCode,
            'daily_hire_rate_usd' => $dailyHireUsd,
            'voyage_days' => $days,
            'demurrage_usd' => $demurrageUsd,
            'total_charter_settlement_usd' => $grandTotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('flt_charter_settlements')->find($id);
    }

    /**
     * Quality audit gate (`marinefleet:audit`).
     */
    public function audit(): array
    {
        $illegalVoyages = DB::table('flt_voyages as v')
            ->join('flt_vessels as s', 'v.imo_number', '=', 's.imo_number')
            ->where('s.safety_certificate_expiry', '<', Carbon::now()->toDateString())
            ->where('v.status', 'SCHEDULED')
            ->count();

        return [
            'status' => $illegalVoyages === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_vessels' => DB::table('flt_vessels')->count(),
            'total_voyages' => DB::table('flt_voyages')->count(),
            'total_settlements' => DB::table('flt_charter_settlements')->count(),
            'discrepancy_count' => $illegalVoyages,
        ];
    }
}
