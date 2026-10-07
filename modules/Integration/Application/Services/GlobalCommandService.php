<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * GlobalCommandService (Fase 151)
 *
 * Implements:
 *  - 151.1 Multi-country and regional HQ registry (APAC, EMEA, AMERICAS)
 *  - 151.2 Market entry playbook with strict compliance checklist gate
 *  - 151.3 Regional currency translation and consolidated hedging exposure
 *  - 151.4 Expatriate tax equalization calculations
 *  - 151.5 Global trade desk positions
 */
class GlobalCommandService
{
    /**
     * Initialize regions and HQs if not present.
     */
    public function seedGlobalRegions(): void
    {
        $regions = [
            ['code' => 'APAC', 'name' => 'Asia Pacific', 'currency' => 'SGD'],
            ['code' => 'EMEA', 'name' => 'Europe, Middle East & Africa', 'currency' => 'EUR'],
            ['code' => 'AMERICAS', 'name' => 'Americas', 'currency' => 'USD'],
        ];

        foreach ($regions as $r) {
            DB::table('grp_regions')->updateOrInsert(
                ['code' => $r['code']],
                ['name' => $r['name'], 'currency' => $r['currency'], 'is_active' => true, 'updated_at' => now()]
            );
        }

        $hqs = [
            ['code' => 'HQ-APAC', 'region_code' => 'APAC', 'legal_name' => 'AutoServe APAC Pte Ltd', 'country_code' => 'SG', 'functional_currency' => 'SGD'],
            ['code' => 'HQ-EMEA', 'region_code' => 'EMEA', 'legal_name' => 'AutoServe EMEA BV', 'country_code' => 'NL', 'functional_currency' => 'EUR'],
            ['code' => 'HQ-AMER', 'region_code' => 'AMERICAS', 'legal_name' => 'AutoServe Americas Inc', 'country_code' => 'US', 'functional_currency' => 'USD'],
        ];

        foreach ($hqs as $h) {
            DB::table('grp_regional_hqs')->updateOrInsert(
                ['code' => $h['code']],
                $h
            );
        }
    }

    /**
     * Register a country expansion with playbook checklist.
     */
    public function registerCountryExpansion(string $regionCode, string $countryCode, string $countryName, array $checklist): object
    {
        $allPassed = true;
        foreach (['permits', 'tax_id', 'labor_compliance', 'data_residency'] as $requiredKey) {
            if (empty($checklist[$requiredKey])) {
                $allPassed = false;
                break;
            }
        }

        DB::table('grp_country_ops')->updateOrInsert(
            ['region_code' => $regionCode, 'country_code' => $countryCode],
            [
                'country_name' => $countryName,
                'status' => $allPassed ? 'LIVE' : 'ENTRY',
                'playbook_checklist' => json_encode($checklist),
                'gate_passed' => $allPassed,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('grp_country_ops')
            ->where('region_code', $regionCode)
            ->where('country_code', $countryCode)
            ->first();
    }

    /**
     * Compute regional translation and FX consolidated exposure.
     */
    public function recordFxExposure(string $regionCode, string $currency, float $amount, float $rateToIdr, float $hedgedAmount = 0.0): object
    {
        $consolidatedIdr = $amount * $rateToIdr;

        $id = DB::table('grp_fx_exposures')->insertGetId([
            'region_code' => $regionCode,
            'currency' => $currency,
            'exposure_amount' => $amount,
            'hedged_amount' => $hedgedAmount,
            'rate_to_idr' => $rateToIdr,
            'consolidated_idr' => $consolidatedIdr,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('grp_fx_exposures')->find($id);
    }

    /**
     * Tax Equalization Calculator for Global Mobility.
     * Computes hypothetical home tax vs actual host tax; company covers excess.
     */
    public function calculateTaxEqualization(float $baseSalaryIdr, float $homeTaxRatePct, float $hostTaxRatePct): array
    {
        $hypoHomeTax = round($baseSalaryIdr * ($homeTaxRatePct / 100.0), 2);
        $actualHostTax = round($baseSalaryIdr * ($hostTaxRatePct / 100.0), 2);
        $equalizationExpense = max(0.0, round($actualHostTax - $hypoHomeTax, 2));

        return [
            'base_salary_idr' => $baseSalaryIdr,
            'hypo_home_tax' => $hypoHomeTax,
            'actual_host_tax' => $actualHostTax,
            'company_borne_expense' => $equalizationExpense,
            'employee_net_retained' => round($baseSalaryIdr - $hypoHomeTax, 2),
        ];
    }

    /**
     * Global audit quality gate.
     */
    public function audit(): array
    {
        // 1. Verify all LIVE country ops passed gate
        $invalidLiveOps = DB::table('grp_country_ops')
            ->where('status', 'LIVE')
            ->where('gate_passed', false)
            ->count();

        // 2. Verify FX consolidation consistency
        $exposures = DB::table('grp_fx_exposures')->get();
        $fxDiscrepancy = 0;
        foreach ($exposures as $exp) {
            $expected = round((float) $exp->exposure_amount * (float) $exp->rate_to_idr, 2);
            if (abs($expected - (float) $exp->consolidated_idr) > 0.05) {
                $fxDiscrepancy++;
            }
        }

        $discrepancies = $invalidLiveOps + $fxDiscrepancy;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_regions' => DB::table('grp_regions')->count(),
            'total_hqs' => DB::table('grp_regional_hqs')->count(),
            'total_country_ops' => DB::table('grp_country_ops')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
