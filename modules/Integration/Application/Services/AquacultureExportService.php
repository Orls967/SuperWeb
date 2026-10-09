<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AquacultureExportService (Fase 171 — Lini 22)
 *
 * Implements:
 *  - 171.1 End-to-end lot passport with immutable lineage hash
 *  - 171.2 Export origin certificate: export quantity <= verified harvest
 *  - 171.3 Blue ESG: water quality & mangrove restoration credit issuance strictly tied to satellite evidence
 */
class AquacultureExportService
{
    /**
     * Create seafood lot passport with lineage chain.
     */
    public function issuePassport(string $hatcheryCode, string $farmCohortCode, float $harvestWeightKg): object
    {
        $code = 'PASS-MAR-'.strtoupper(Str::random(8));
        $hash = hash('sha256', "{$code}:{$hatcheryCode}:{$farmCohortCode}:{$harvestWeightKg}");

        $id = DB::table('mar_lot_passports')->insertGetId([
            'passport_code' => $code,
            'hatchery_code' => $hatcheryCode,
            'farm_cohort_code' => $farmCohortCode,
            'verified_harvest_weight_kg' => $harvestWeightKg,
            'lineage_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('mar_lot_passports')->find($id);
    }

    /**
     * Issue export certificate. Enforces export quantity <= verified harvest weight.
     */
    public function issueExportCertificate(string $passportCode, float $exportQtyKg, string $destCountryIso): object
    {
        $passport = DB::table('mar_lot_passports')->where('passport_code', $passportCode)->first();
        if (! $passport) {
            throw new \InvalidArgumentException("Passport {$passportCode} not found.");
        }

        $maxWeight = (float) $passport->verified_harvest_weight_kg;
        if ($exportQtyKg > $maxWeight) {
            throw new \RuntimeException("Export quantity ({$exportQtyKg} kg) exceeds verified harvest weight ({$maxWeight} kg).");
        }

        $certNum = 'EXP-COO-'.strtoupper(Str::random(10));

        $id = DB::table('mar_export_certificates')->insertGetId([
            'certificate_number' => $certNum,
            'passport_code' => $passportCode,
            'export_quantity_kg' => $exportQtyKg,
            'destination_country_iso' => strtoupper($destCountryIso),
            'health_cert_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('mar_export_certificates')->find($id);
    }

    /**
     * Issue Blue ESG credit tied to satellite evidence and water quality.
     */
    public function issueBlueCarbonCredit(string $mangrovePolygonId, float $waterIndex, float $carbonTons, bool $hasSatelliteEvidence): object
    {
        if (! $hasSatelliteEvidence) {
            throw new \RuntimeException('Blue ESG credit issuance rejected: Satellite evidence required.');
        }

        $code = 'BLUE-CRD-'.strtoupper(Str::random(8));

        $id = DB::table('mar_blue_esg_credits')->insertGetId([
            'credit_code' => $code,
            'mangrove_restoration_polygon_id' => $mangrovePolygonId,
            'water_quality_index' => $waterIndex,
            'blue_carbon_tons' => $carbonTons,
            'has_satellite_evidence' => true,
            'issued' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('mar_blue_esg_credits')->find($id);
    }

    /**
     * Audit: verify export quantity <= verified harvest across all certs.
     */
    public function audit(): array
    {
        $excessCerts = DB::table('mar_export_certificates as c')
            ->join('mar_lot_passports as p', 'c.passport_code', '=', 'p.passport_code')
            ->whereRaw('c.export_quantity_kg > p.verified_harvest_weight_kg')
            ->count();

        return [
            'status' => $excessCerts === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_passports' => DB::table('mar_lot_passports')->count(),
            'total_export_certs' => DB::table('mar_export_certificates')->count(),
            'total_blue_credits' => DB::table('mar_blue_esg_credits')->count(),
            'discrepancy_count' => $excessCerts,
        ];
    }
}
