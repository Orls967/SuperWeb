<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CircularEconomyService (Fase 174 — Lini 24)
 *
 * Implements:
 *  - 174.1 Recycler facilities with hazardous B3 permit gating
 *  - 174.3 Waste manifests with strict hazardous routing rejection for unlicensed operators
 *  - 174.4 Circularity treatment accounting with mass-balance validation
 */
class CircularEconomyService
{
    /**
     * Register recycler facility and licensed permits.
     */
    public function registerFacility(string $code, string $operator, bool $hasHazardousPermit): object
    {
        DB::table('cir_recycler_facilities')->updateOrInsert(
            ['facility_code' => $code],
            [
                'operator_name' => $operator,
                'has_hazardous_permit' => $hasHazardousPermit,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('cir_recycler_facilities')->where('facility_code', $code)->first();
    }

    /**
     * Create waste transfer manifest.
     * Enforces hazardous waste routing gate: rejects if facility lacks B3 hazardous permit.
     */
    public function createManifest(string $sourceEntity, string $facilityCode, string $wasteType, bool $isHazardous, float $weightTons): object
    {
        $facility = DB::table('cir_recycler_facilities')->where('facility_code', $facilityCode)->first();
        if (! $facility) {
            throw new \InvalidArgumentException("Facility {$facilityCode} not found.");
        }

        if ($isHazardous && ! (bool) $facility->has_hazardous_permit) {
            throw new \RuntimeException("Routing rejected: Hazardous waste cannot be transferred to facility {$facilityCode} without valid B3 permit.");
        }

        $code = 'MAN-CIR-'.strtoupper(Str::random(8));
        $hash = hash('sha256', "{$code}:{$sourceEntity}:{$facilityCode}:{$wasteType}:{$weightTons}");

        $id = DB::table('cir_waste_manifests')->insertGetId([
            'manifest_code' => $code,
            'source_entity_code' => strtoupper($sourceEntity),
            'facility_code' => $facilityCode,
            'waste_type' => strtoupper($wasteType),
            'is_hazardous' => $isHazardous,
            'weight_tons' => $weightTons,
            'manifest_hash' => $hash,
            'status' => 'DISPATCHED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cir_waste_manifests')->find($id);
    }

    /**
     * Record circularity treatment with mass-balance validation.
     * Invarian: input_weight = recycled_material + residual_waste (tolerance <= 1%).
     */
    public function recordTreatment(string $manifestCode, float $inputTons, float $recycledTons, float $residualTons): object
    {
        $totalOutput = $recycledTons + $residualTons;
        $diff = abs($inputTons - $totalOutput);
        $diffPct = $inputTons > 0 ? ($diff / $inputTons) * 100.0 : 0.0;
        $isValid = ($diffPct <= 1.0);

        $code = 'TRT-'.strtoupper(Str::random(8));

        $id = DB::table('cir_treatment_records')->insertGetId([
            'treatment_code' => $code,
            'manifest_code' => $manifestCode,
            'input_weight_tons' => $inputTons,
            'recycled_material_tons' => $recycledTons,
            'residual_waste_tons' => $residualTons,
            'mass_balance_valid' => $isValid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cir_treatment_records')->find($id);
    }

    /**
     * Quality audit gate (`circular:audit`).
     */
    public function audit(): array
    {
        $illegalHazardous = DB::table('cir_waste_manifests as m')
            ->join('cir_recycler_facilities as f', 'm.facility_code', '=', 'f.facility_code')
            ->where('m.is_hazardous', true)
            ->where('f.has_hazardous_permit', false)
            ->count();

        $invalidMassBalance = DB::table('cir_treatment_records')
            ->where('mass_balance_valid', false)
            ->count();

        $discrepancies = $illegalHazardous + $invalidMassBalance;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_facilities' => DB::table('cir_recycler_facilities')->count(),
            'total_manifests' => DB::table('cir_waste_manifests')->count(),
            'total_treatment_records' => DB::table('cir_treatment_records')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
