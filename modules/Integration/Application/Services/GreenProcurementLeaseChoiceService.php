<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GreenProcurementLeaseChoiceService (Fase 332)
 *
 * Implements:
 *  - 332.1 Green supplier RFQ evaluation with minimum compliance gates and weighted scoring
 *  - 332.2 Green lease & utility performance incentives tied to verified reductions
 *  - 332.4 Tests: RFQ criteria reproducible; incentive equals verified performance; esg:audit clean
 *  - 332.5 Edge case: Tenant refusing green target transitions to alternate service tier (never silently forced)
 *  - 332.6 Risk: Green claims/labels strictly bound to verified measurements without unsupported narrative
 */
class GreenProcurementLeaseChoiceService
{
    /**
     * Evaluate RFQ supplier on green criteria with compliance gate (332.1 & 332.4).
     */
    public function evaluateSupplierRfq(
        string $evaluationCode,
        string $rfqCode,
        string $supplierId,
        bool $passedComplianceGate,
        float $carbonScore,
        float $circularityScore,
        bool $isAwarded = false
    ): object {
        $eCode = strtoupper($evaluationCode);
        $rCode = strtoupper($rfqCode);

        // Compliance gate check 332.1: Failing minimum compliance gate blocks award
        if (! $passedComplianceGate && $isAwarded) {
            throw new InvalidArgumentException("Green procurement breach: Supplier failing minimum compliance gate cannot be awarded contract (332.1).");
        }

        // Weighted score 332.1: 60% Carbon score + 40% Circularity score
        $weighted = round(($carbonScore * 0.60) + ($circularityScore * 0.40), 1);

        $id = DB::table('green_procurement_rfq_evaluations')->insertGetId([
            'evaluation_code' => $eCode,
            'rfq_code' => $rCode,
            'supplier_id' => strtoupper($supplierId),
            'passed_compliance_gate' => $passedComplianceGate,
            'carbon_efficiency_score' => $carbonScore,
            'circularity_score' => $circularityScore,
            'weighted_green_score' => $weighted,
            'is_awarded' => $isAwarded,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('green_procurement_rfq_evaluations')->find($id);
    }

    /**
     * Settle green lease performance incentive with tenant choice negotiation handling (332.2, 332.4, 332.5 Edge Case).
     */
    public function settleGreenLeaseIncentive(
        string $leaseCode,
        string $tenantId,
        string $facilityCode,
        bool $tenantAcceptedTarget,
        float $verifiedReductionPct,
        float $baseRentUsd,
        bool $performanceVerified = true
    ): object {
        $lCode = strtoupper($leaseCode);

        // Verification check 332.4: Incentive requires verified performance
        if (! $performanceVerified) {
            throw new InvalidArgumentException("Lease incentive violation: Rebates require verified sub-meter performance data (332.4).");
        }

        // Edge case 332.5: If tenant refused green target, assign alternate tier with zero incentive rebate
        if (! $tenantAcceptedTarget) {
            $serviceTier = 'ALTERNATE_CONVENTIONAL_TIER';
            $rebate = 0.00;
        } else {
            $serviceTier = 'STANDARD_GREEN_TIER';
            // Incentive calculation: e.g. 5% rebate if reduction >= 15%
            $rebatePct = ($verifiedReductionPct >= 15.00) ? 5.00 : 0.00;
            $rebate = round($baseRentUsd * ($rebatePct / 100.0), 2);
        }

        $id = DB::table('green_lease_performance_incentives')->insertGetId([
            'lease_code' => $lCode,
            'tenant_id' => strtoupper($tenantId),
            'facility_code' => strtoupper($facilityCode),
            'tenant_accepted_green_target' => $tenantAcceptedTarget,
            'service_tier' => $serviceTier,
            'verified_energy_reduction_pct' => $verifiedReductionPct,
            'incentive_rebate_usd' => $rebate,
            'performance_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('green_lease_performance_incentives')->find($id);
    }

    /**
     * ESG Procurement & Lease Audit (`esg:audit`) (332.4, 332.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Awarded suppliers who failed compliance gate
        $improperAwards = DB::table('green_procurement_rfq_evaluations')
            ->where('passed_compliance_gate', false)
            ->where('is_awarded', true)
            ->count();

        // Discrepancy 2: Unverified lease performance receiving rebates
        $unverifiedRebates = DB::table('green_lease_performance_incentives')
            ->where('performance_verified', false)
            ->where('incentive_rebate_usd', '>', 0)
            ->count();

        $discrepancies = $improperAwards + $unverifiedRebates;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_rfq_evaluations' => DB::table('green_procurement_rfq_evaluations')->count(),
            'total_leases' => DB::table('green_lease_performance_incentives')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
