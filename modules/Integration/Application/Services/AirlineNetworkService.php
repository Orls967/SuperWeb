<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AirlineNetworkService (Fase 178 — Lini 26)
 *
 * Implements:
 *  - 178.2 Yield management fare quotes respecting regulatory/contract floor guardrails
 *  - 178.3 Loyalty miles earning with anti-duplicate codeshare keys & liability tracking
 *  - 178.4 Irregular operations disruption care and statutory compensation settlement
 */
class AirlineNetworkService
{
    /**
     * Generate dynamic fare quote. Enforces regulatory floor guardrails (fare >= floor).
     */
    public function quoteDynamicFare(string $routeCode, float $baseFare, float $demandMultiplier, float $floorPrice): object
    {
        $calculatedFare = round($baseFare * $demandMultiplier, 2);
        // Floor guardrail: cannot sell below minimum floor price
        $effectiveFare = max($calculatedFare, $floorPrice);

        $code = 'QTE-AVI-'.strtoupper(Str::random(8));

        $id = DB::table('avi_fare_quotes')->insertGetId([
            'quote_code' => $code,
            'route_code' => strtoupper($routeCode),
            'floor_price_idr' => $floorPrice,
            'quoted_fare_idr' => $effectiveFare,
            'floor_price_respected' => ($effectiveFare >= $floorPrice),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('avi_fare_quotes')->find($id);
    }

    /**
     * Credit loyalty miles with idempotent deduplication across partner codeshares.
     */
    public function creditLoyaltyMiles(string $earningKey, int $memberId, string $flightCode, int $miles): object
    {
        $existing = DB::table('avi_loyalty_miles_ledgers')->where('earning_key', $earningKey)->first();
        if ($existing) {
            return (object) $existing; // Idempotent return to prevent duplicate crediting
        }

        $liabilityVal = round($miles * 150.0, 2); // Rp 150 liability per mile

        $id = DB::table('avi_loyalty_miles_ledgers')->insertGetId([
            'earning_key' => $earningKey,
            'member_id' => $memberId,
            'flight_code' => $flightCode,
            'miles_earned' => $miles,
            'liability_value_idr' => $liabilityVal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('avi_loyalty_miles_ledgers')->find($id);
    }

    /**
     * Log irregular operations flight disruption and compute statutory passenger compensation pool.
     */
    public function logFlightDisruption(string $flightCode, string $reason, int $impactedPax, float $compPerPax = 300000.0): object
    {
        $poolTotal = round($impactedPax * $compPerPax, 2);
        $code = 'DIS-AVI-'.strtoupper(Str::random(8));

        $id = DB::table('avi_disruptions')->insertGetId([
            'disruption_code' => $code,
            'flight_code' => $flightCode,
            'reason' => strtoupper($reason),
            'statutory_compensation_per_pax_idr' => $compPerPax,
            'impacted_pax_count' => $impactedPax,
            'total_compensation_pool_idr' => $poolTotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('avi_disruptions')->find($id);
    }

    /**
     * Quality audit gate (`avi:audit`).
     */
    public function audit(): array
    {
        $floorBreaches = DB::table('avi_fare_quotes')
            ->where('floor_price_respected', false)
            ->count();

        return [
            'status' => $floorBreaches === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_quotes' => DB::table('avi_fare_quotes')->count(),
            'total_miles_transactions' => DB::table('avi_loyalty_miles_ledgers')->count(),
            'total_disruptions' => DB::table('avi_disruptions')->count(),
            'discrepancy_count' => $floorBreaches,
        ];
    }
}
