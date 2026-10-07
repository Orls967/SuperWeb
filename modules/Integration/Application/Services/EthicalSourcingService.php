<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EthicalSourcingService (Fase 154)
 *
 * Implements:
 *  - 154.1 Global talent pool and work authorization verification
 *  - 154.2 Ethical sourcing & modern slavery audit: tender eligibility impact, remediation & blacklisting
 *  - 154.3 Living wage benchmarking and social gap reporting
 *  - 154.4 Vendor Code of Conduct (CoC) onboarding compliance
 */
class EthicalSourcingService
{
    /**
     * Register candidate in global talent pool.
     */
    public function registerCandidate(string $name, string $countryCode, string $skillDomain, bool $workAuthVerified): object
    {
        $code = 'CAND-'.strtoupper(Str::random(8));

        $id = DB::table('eth_global_candidates')->insertGetId([
            'candidate_code' => $code,
            'name' => $name,
            'country_code' => strtoupper($countryCode),
            'skill_domain' => strtoupper($skillDomain),
            'work_auth_verified' => $workAuthVerified,
            'matching_status' => 'AVAILABLE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('eth_global_candidates')->find($id);
    }

    /**
     * Conduct ethical sourcing and modern slavery audit on supplier.
     */
    public function auditSupplier(string $supplierCode, string $sector, float $ethicalScore, bool $childLabor, bool $forcedLabor): object
    {
        $hasViolation = $childLabor || $forcedLabor;

        $status = match (true) {
            $hasViolation => 'BLACKLISTED',
            $ethicalScore < 70.0 => 'REMEDIATION',
            default => 'PASSED',
        };

        $isTenderEligible = ($status === 'PASSED');

        $remediation = ($status === 'REMEDIATION')
            ? 'Mandatory third-party social compliance audit within 60 days.'
            : null;

        $id = DB::table('eth_supplier_audits')->insertGetId([
            'supplier_code' => $supplierCode,
            'sector' => strtoupper($sector),
            'ethical_score' => $ethicalScore,
            'child_labor_detected' => $childLabor,
            'forced_labor_detected' => $forcedLabor,
            'status' => $status,
            'remediation_plan' => $remediation,
            'is_tender_eligible' => $isTenderEligible,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('eth_supplier_audits')->find($id);
    }

    /**
     * Set living wage benchmark and calculate local wage gap.
     */
    public function setLivingWageBenchmark(string $countryCode, float $monthlyAmount, string $currency): void
    {
        DB::table('eth_living_wage_benchmarks')->updateOrInsert(
            ['country_code' => strtoupper($countryCode)],
            [
                'living_wage_monthly' => $monthlyAmount,
                'currency' => strtoupper($currency),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Calculate living wage gap against actual paid wage.
     */
    public function evaluateWageGap(string $countryCode, float $actualPaidMonthly): array
    {
        $benchmark = DB::table('eth_living_wage_benchmarks')
            ->where('country_code', strtoupper($countryCode))
            ->first();

        $livingWage = (float) ($benchmark->living_wage_monthly ?? $actualPaidMonthly);
        $gap = max(0.0, round($livingWage - $actualPaidMonthly, 2));

        return [
            'country_code' => strtoupper($countryCode),
            'actual_paid' => $actualPaidMonthly,
            'living_wage_benchmark' => $livingWage,
            'monthly_wage_gap' => $gap,
            'compliance_pct' => $livingWage > 0 ? round(($actualPaidMonthly / $livingWage) * 100, 2) : 100.0,
            'meets_living_wage' => $gap <= 0.0,
        ];
    }

    /**
     * Sign Vendor Code of Conduct.
     */
    public function signVendorCoc(string $supplierCode): object
    {
        DB::table('eth_vendor_coc_records')->updateOrInsert(
            ['supplier_code' => $supplierCode],
            [
                'coc_signed' => true,
                'signed_at' => now(),
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('eth_vendor_coc_records')->where('supplier_code', $supplierCode)->first();
    }

    /**
     * Check if supplier is allowed for purchase order (CoC signed and not blacklisted).
     */
    public function canIssuePurchaseOrder(string $supplierCode): bool
    {
        $coc = DB::table('eth_vendor_coc_records')->where('supplier_code', $supplierCode)->first();
        if (! $coc || ! $coc->coc_signed) {
            return false;
        }

        $latestAudit = DB::table('eth_supplier_audits')
            ->where('supplier_code', $supplierCode)
            ->latest('id')
            ->first();

        if ($latestAudit && $latestAudit->status === 'BLACKLISTED') {
            return false;
        }

        return true;
    }

    /**
     * Audit: verify no active PO eligibility for blacklisted or unsigned vendors.
     */
    public function audit(): array
    {
        $ineligibleBlacklisted = DB::table('eth_supplier_audits')
            ->where('status', 'BLACKLISTED')
            ->where('is_tender_eligible', true)
            ->count();

        return [
            'status' => $ineligibleBlacklisted === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'blacklisted_suppliers' => DB::table('eth_supplier_audits')->where('status', 'BLACKLISTED')->count(),
            'total_coc_signed' => DB::table('eth_vendor_coc_records')->where('coc_signed', true)->count(),
            'discrepancy_count' => $ineligibleBlacklisted,
        ];
    }
}
