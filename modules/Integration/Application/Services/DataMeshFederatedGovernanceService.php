<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * DataMeshFederatedGovernanceService (Fase 267)
 *
 * Implements:
 *  - 267.1 Domain-owned data products (across 30 enterprise lines) with federated computational policy engines & minimum platform bar
 *  - 267.2 Self-serve data product publishing with automated policy compliance verification
 *  - 267.3 Data product interoperability contracts with internal SLA and usage-based chargebacks
 *  - 267.5 Edge case: Data products failing minimum platform quality bar (score < 80.0) are strictly blocked from publishing
 *  - 267.6 Quality SLA breaches automatically issue consumer notices & usage credit compensations
 *  - 267.7 New domain onboarding strictly mandates governance templates
 */
class DataMeshFederatedGovernanceService
{
    /**
     * Publish domain-owned data product enforcing minimum platform bar (267.1, 267.2, 267.5 Edge Case, 267.7).
     */
    public function publishDataProduct(
        string $productCode,
        string $domainName,
        string $productTitle,
        float $qualityScore,
        bool $hasGovernanceTemplate = true
    ): object {
        $code = strtoupper($productCode);
        $meetsBar = ($qualityScore >= 80.0 && $hasGovernanceTemplate);

        // Edge case 267.5: Minimum bar violation blocks publishing
        if (! $meetsBar) {
            $id = DB::table('datamesh_domain_products')->insertGetId([
                'product_code' => $code,
                'domain_name' => strtoupper($domainName),
                'product_title' => $productTitle,
                'schema_version' => 1,
                'quality_score' => $qualityScore,
                'meets_minimum_bar' => false,
                'publishing_blocked' => true,
                'publishing_status' => 'BLOCKED_POLICY_VIOLATION',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Platform policy violation: Data product '{$productCode}' blocked from publishing (Quality score {$qualityScore} < 80.0 or missing governance template) (267.5 & 267.7).");
        }

        $id = DB::table('datamesh_domain_products')->insertGetId([
            'product_code' => $code,
            'domain_name' => strtoupper($domainName),
            'product_title' => $productTitle,
            'schema_version' => 1,
            'quality_score' => $qualityScore,
            'meets_minimum_bar' => true,
            'publishing_blocked' => false,
            'publishing_status' => 'PUBLISHED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('datamesh_domain_products')->find($id);
    }

    /**
     * Create interoperability contract between producer and consumer domain (267.3).
     */
    public function createInteroperabilityContract(
        string $contractCode,
        string $productCode,
        string $consumerDomain,
        int $targetSlaFreshnessMins = 60
    ): object {
        $code = strtoupper($contractCode);

        $id = DB::table('datamesh_interoperability_contracts')->insertGetId([
            'contract_code' => $code,
            'product_code' => strtoupper($productCode),
            'consumer_domain' => strtoupper($consumerDomain),
            'target_sla_freshness_mins' => $targetSlaFreshnessMins,
            'actual_freshness_mins' => 30,
            'is_sla_breached' => false,
            'compensation_credit_usd' => 0.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('datamesh_interoperability_contracts')->find($id);
    }

    /**
     * Record SLA measurement & grant automatic compensation credit upon breach (267.4 & 267.6 Edge Case).
     */
    public function recordSlaMeasurement(
        string $contractCode,
        int $actualFreshnessMins
    ): object {
        $code = strtoupper($contractCode);
        $contract = DB::table('datamesh_interoperability_contracts')->where('contract_code', $code)->first();
        if (! $contract) {
            throw new InvalidArgumentException("Contract '{$contractCode}' not found.");
        }

        $target = (int) $contract->target_sla_freshness_mins;
        $isBreached = ($actualFreshnessMins > $target);
        // Automatic $250.00 compensation credit per breach (267.6)
        $credit = $isBreached ? 250.00 : 0.00;

        DB::table('datamesh_interoperability_contracts')
            ->where('contract_code', $code)
            ->update([
                'actual_freshness_mins' => $actualFreshnessMins,
                'is_sla_breached' => $isBreached,
                'compensation_credit_usd' => $credit,
                'updated_at' => now(),
            ]);

        return (object) DB::table('datamesh_interoperability_contracts')->where('contract_code', $code)->first();
    }

    /**
     * Bill data product usage factoring in compensation credits (267.3 & 267.4).
     */
    public function billProductUsage(
        string $contractCode,
        int $queryUnitsConsumed,
        float $ratePerUnitUsd = 0.0500
    ): object {
        $code = strtoupper($contractCode);
        $contract = DB::table('datamesh_interoperability_contracts')->where('contract_code', $code)->first();
        if (! $contract) {
            throw new InvalidArgumentException("Contract '{$contractCode}' not found.");
        }

        $gross = round($queryUnitsConsumed * $ratePerUnitUsd, 2);
        $credit = (float) $contract->compensation_credit_usd;
        $net = max(0.0, round($gross - $credit, 2));

        $billingCode = 'BILL-'.strtoupper(Str::random(8));

        $id = DB::table('datamesh_product_usage_billing')->insertGetId([
            'billing_code' => $billingCode,
            'contract_code' => $code,
            'query_units_consumed' => $queryUnitsConsumed,
            'rate_per_unit_usd' => $ratePerUnitUsd,
            'gross_amount_usd' => $gross,
            'net_billed_amount_usd' => $net,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('datamesh_product_usage_billing')->find($id);
    }

    /**
     * Data Mesh Federated Governance Platform Audit (`mesh:audit`) (267.4, 267.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Products published despite failing minimum quality bar
        $unauthorizedPublishes = DB::table('datamesh_domain_products')
            ->where('publishing_status', 'PUBLISHED')
            ->where('meets_minimum_bar', false)
            ->count();

        // Discrepancy 2: SLA breaches without compensation credit
        $uncompensatedBreaches = DB::table('datamesh_interoperability_contracts')
            ->where('is_sla_breached', true)
            ->where('compensation_credit_usd', '<=', 0.0)
            ->count();

        // Discrepancy 3: Billing net calculation error
        $mathErrors = 0;
        $bills = DB::table('datamesh_product_usage_billing')->get();
        foreach ($bills as $bill) {
            $expectedGross = round((int) $bill->query_units_consumed * (float) $bill->rate_per_unit_usd, 2);
            if (abs($expectedGross - (float) $bill->gross_amount_usd) > 0.01) {
                $mathErrors++;
            }
        }

        $discrepancies = $unauthorizedPublishes + $uncompensatedBreaches + $mathErrors;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_products' => DB::table('datamesh_domain_products')->count(),
            'total_contracts' => DB::table('datamesh_interoperability_contracts')->count(),
            'total_billings' => DB::table('datamesh_product_usage_billing')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
