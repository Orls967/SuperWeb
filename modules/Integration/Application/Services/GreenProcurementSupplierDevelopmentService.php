<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GreenProcurementSupplierDevelopmentService (Fase 447)
 *
 * Implements:
 *  - 447.1 Supplier sustainability questionnaire, risk tiers (tier_1_green, tier_2_neutral, tier_3_lagging)
 *  - 447.2 Supplier decarbonization program with contractual target clauses
 *  - 447.3 Green premium/discount tied to verified sustainability data
 *  - 447.4 Tests: tier weighting affects evaluation, target clause enforced, supplier:audit clean
 *  - 447.5 Edge case: Supplier refusing decarbonization target receives phased tier downgrade rather than immediate blacklist
 *  - 447.6 Risk: Green premium/discount without verified footprint data is strictly blocked (anti-greenwashing)
 *  - 447.7 Evidence: questionnaire score, target clause, evaluation weighting
 */
class GreenProcurementSupplierDevelopmentService
{
    public function evaluateSupplier(
        string $supplierCode,
        string $supplierName,
        float $sustainabilityScore,
        bool $hasVerifiedData = false
    ): object {
        $tier = 'tier_2_neutral';
        if ($sustainabilityScore >= 80.00) {
            $tier = 'tier_1_green';
        } elseif ($sustainabilityScore < 50.00) {
            $tier = 'tier_3_lagging';
        }

        $id = DB::table('esg_green_supplier_evaluations')->insertGetId([
            'supplier_code' => strtoupper($supplierCode),
            'supplier_name' => $supplierName,
            'sustainability_score' => $sustainabilityScore,
            'risk_tier' => $tier,
            'green_discount_premium_percent' => 0.00,
            'has_verified_footprint_data' => $hasVerifiedData,
            'decarbonization_clause_agreed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_green_supplier_evaluations')->where('id', $id)->first();
    }

    /**
     * 447.3 & 447.6 Apply green discount/premium strictly requiring verified footprint data
     */
    public function applyGreenDiscountPremium(string $supplierCode, float $percent): object
    {
        $supp = DB::table('esg_green_supplier_evaluations')->where('supplier_code', strtoupper($supplierCode))->first();
        if (! $supp) {
            throw new InvalidArgumentException("Supplier '{$supplierCode}' not found.");
        }

        // 447.6 Risk: Anti-greenwashing gate
        if (! $supp->has_verified_footprint_data) {
            throw new InvalidArgumentException('Greenwashing blocked: Green discount/premium requires third-party verified environmental footprint data (447.3, 447.6).');
        }

        DB::table('esg_green_supplier_evaluations')->where('id', $supp->id)->update([
            'green_discount_premium_percent' => $percent,
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_green_supplier_evaluations')->where('id', $supp->id)->first();
    }

    /**
     * 447.2, 447.4, 447.5 Contractual decarbonization clause negotiation
     */
    public function handleDecarbonizationNegotiation(string $supplierCode, bool $agreed): object
    {
        $supp = DB::table('esg_green_supplier_evaluations')->where('supplier_code', strtoupper($supplierCode))->first();
        if (! $supp) {
            throw new InvalidArgumentException("Supplier '{$supplierCode}' not found.");
        }

        if ($agreed) {
            DB::table('esg_green_supplier_evaluations')->where('id', $supp->id)->update([
                'decarbonization_clause_agreed' => true,
                'updated_at' => now(),
            ]);
        } else {
            // 447.5 Edge case: Refusal leads to phased downgrade, not instant blacklisting
            $newTier = ($supp->risk_tier === 'tier_1_green') ? 'tier_2_neutral' : 'tier_3_lagging';
            DB::table('esg_green_supplier_evaluations')->where('id', $supp->id)->update([
                'decarbonization_clause_agreed' => false,
                'risk_tier' => $newTier,
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('esg_green_supplier_evaluations')->where('id', $supp->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Green discounts granted without verified footprint data
        $unverifiedGreenwasing = DB::table('esg_green_supplier_evaluations')
            ->where('green_discount_premium_percent', '!=', 0.00)
            ->where('has_verified_footprint_data', false)
            ->count();

        return [
            'status' => $unverifiedGreenwasing === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_suppliers' => DB::table('esg_green_supplier_evaluations')->count(),
            'discrepancy_count' => $unverifiedGreenwasing,
        ];
    }
}
