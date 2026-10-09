<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MarineAquacultureService (Fase 170 — Lini 22)
 *
 * Implements:
 *  - 170.1 Marine cohorts with biomass conservation (harvest <= total biomass)
 *  - 170.3 Harvest lots with cold-chain breach detection & quarantine isolation
 *  - 170.3 Sustainable fishing zone harvest quota enforcement
 */
class MarineAquacultureService
{
    /**
     * Create marine cohort.
     */
    public function createCohort(string $species, float $biomassKg): object
    {
        $code = 'CHR-'.strtoupper(Str::random(8));

        $id = DB::table('mar_cohorts')->insertGetId([
            'cohort_code' => $code,
            'species_name' => strtoupper($species),
            'total_biomass_kg' => $biomassKg,
            'harvested_biomass_kg' => 0.00,
            'health_status' => 'HEALTHY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('mar_cohorts')->find($id);
    }

    /**
     * Register sustainable quota for fishing zone.
     */
    public function setZoneQuota(string $zoneCode, float $annualQuotaKg): object
    {
        DB::table('mar_sustainable_quotas')->updateOrInsert(
            ['zone_code' => strtoupper($zoneCode)],
            [
                'annual_quota_kg' => $annualQuotaKg,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('mar_sustainable_quotas')->where('zone_code', strtoupper($zoneCode))->first();
    }

    /**
     * Harvest from cohort and enforce biomass limit, quota limit, and cold-chain temperature safety.
     */
    public function recordHarvest(string $cohortCode, string $zoneCode, float $harvestWeightKg, float $coldChainTempC): object
    {
        $cohort = DB::table('mar_cohorts')->where('cohort_code', $cohortCode)->first();
        if (! $cohort) {
            throw new \InvalidArgumentException("Cohort {$cohortCode} not found.");
        }

        $availableBiomass = (float) $cohort->total_biomass_kg - (float) $cohort->harvested_biomass_kg;
        if ($harvestWeightKg > $availableBiomass) {
            throw new \RuntimeException("Biomass exceeded: Harvest {$harvestWeightKg} kg exceeds available cohort biomass {$availableBiomass} kg.");
        }

        // Zone quota check
        $quota = DB::table('mar_sustainable_quotas')->where('zone_code', strtoupper($zoneCode))->first();
        if ($quota) {
            $accum = (float) $quota->accumulated_harvest_kg;
            $maxQuota = (float) $quota->annual_quota_kg;
            if ($accum + $harvestWeightKg > $maxQuota) {
                throw new \RuntimeException("Zone quota breached: Total harvest would exceed {$maxQuota} kg.");
            }
        }

        // Cold-chain safety: Seafood must be stored <= -18°C or fresh <= 4°C
        $isBreached = ($coldChainTempC > 4.0);
        $lotCode = 'LOT-MAR-'.strtoupper(Str::random(8));

        $id = DB::table('mar_harvest_lots')->insertGetId([
            'harvest_lot_code' => $lotCode,
            'cohort_code' => $cohortCode,
            'harvest_weight_kg' => $harvestWeightKg,
            'cold_chain_temp_c' => $coldChainTempC,
            'cold_chain_breached' => $isBreached,
            'status' => $isBreached ? 'QUARANTINED' : 'CLEARED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update cohort
        DB::table('mar_cohorts')->where('cohort_code', $cohortCode)->update([
            'harvested_biomass_kg' => DB::raw("harvested_biomass_kg + {$harvestWeightKg}"),
            'updated_at' => now(),
        ]);

        // Update quota if tracked
        if ($quota) {
            DB::table('mar_sustainable_quotas')->where('zone_code', strtoupper($zoneCode))->update([
                'accumulated_harvest_kg' => DB::raw("accumulated_harvest_kg + {$harvestWeightKg}"),
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('mar_harvest_lots')->find($id);
    }

    /**
     * Quality audit gate (`marine:audit`).
     */
    public function audit(): array
    {
        $excessHarvest = DB::table('mar_cohorts')
            ->whereRaw('harvested_biomass_kg > total_biomass_kg')
            ->count();

        return [
            'status' => $excessHarvest === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_cohorts' => DB::table('mar_cohorts')->count(),
            'total_harvest_lots' => DB::table('mar_harvest_lots')->count(),
            'total_quotas' => DB::table('mar_sustainable_quotas')->count(),
            'discrepancy_count' => $excessHarvest,
        ];
    }
}
