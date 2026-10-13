<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CustomerLoyaltySubscriptionEconomyService (Fase 378)
 *
 * Implements:
 *  - 378.1 Unified customer identity and consent-aware entitlements
 *  - 378.2 Intercompany benefit redemptions split and balanced to net zero
 *  - 378.4 Tests: Consent enforced; intercompany split balances; crm:audit clean
 *  - 378.5 Edge case: Cross-line entitlement conflicts must resolve to single group policy rule
 *  - 378.6 Risk: Liability imbalances prevented by exact balancing splits
 */
class CustomerLoyaltySubscriptionEconomyService
{
    /**
     * Grant cross-line entitlement with consent verification and unified conflict policy (378.1, 378.4, 378.5 Edge Case).
     */
    public function grantCrossLineEntitlement(
        string $entitlementCode,
        string $customerId,
        bool $customerConsentActive,
        string $singlePolicyRuleId
    ): object {
        $eCode = strtoupper($entitlementCode);
        $cId = strtoupper($customerId);

        // Core gate 378.4: Customer consent required
        if (! $customerConsentActive) {
            throw new InvalidArgumentException("Consent missing: Customer '{$customerId}' has not granted consent for cross-line entitlements (378.4).");
        }

        // Edge case 378.5: Must reference single unified policy rule rather than conflicting line rules
        if (empty($singlePolicyRuleId) || str_contains($singlePolicyRuleId, 'CONFLICT')) {
            throw new InvalidArgumentException('Policy conflict: Entitlement requires resolution via single unified group policy (378.5).');
        }

        $id = DB::table('global_customer_cross_line_entitlements')->insertGetId([
            'entitlement_code' => $eCode,
            'customer_id' => $cId,
            'customer_consent_active' => true,
            'primary_policy_rule_id' => strtoupper($singlePolicyRuleId),
            'conflict_resolved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_customer_cross_line_entitlements')->find($id);
    }

    /**
     * Settle intercompany benefit redemption split balancing exactly to gross amount (378.2 & 378.4).
     */
    public function settleIntercompanyRedemption(
        string $redemptionCode,
        string $customerId,
        float $grossAmount,
        float $entityAShare,
        float $entityBShare
    ): object {
        $rCode = strtoupper($redemptionCode);
        $cId = strtoupper($customerId);

        $splitSum = round($entityAShare + $entityBShare, 2);
        $grossRounded = round($grossAmount, 2);

        // Core gate 378.4: Intercompany split must balance exactly
        if (abs($splitSum - $grossRounded) > 0.001) {
            throw new InvalidArgumentException("Intercompany imbalance: Sum of entity shares (\${$splitSum}) does not match gross redemption amount (\${$grossRounded}) (378.4).");
        }

        $id = DB::table('global_customer_intercompany_redemptions')->insertGetId([
            'redemption_code' => $rCode,
            'customer_id' => $cId,
            'gross_redemption_amount' => $grossRounded,
            'entity_a_share_amount' => round($entityAShare, 2),
            'entity_b_share_amount' => round($entityBShare, 2),
            'intercompany_balanced' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_customer_intercompany_redemptions')->find($id);
    }

    /**
     * Customer CRM & Loyalty Audit (`crm:audit`) (378.4, 378.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Entitlements granted without active customer consent
        $unconsentedEntitlements = DB::table('global_customer_cross_line_entitlements')
            ->where('customer_consent_active', false)
            ->count();

        // Discrepancy 2: Redemptions with unbalanced intercompany split
        $unbalancedRedemptions = DB::table('global_customer_intercompany_redemptions')
            ->where('intercompany_balanced', false)
            ->count();

        $discrepancies = $unconsentedEntitlements + $unbalancedRedemptions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_entitlements' => DB::table('global_customer_cross_line_entitlements')->count(),
            'total_redemptions' => DB::table('global_customer_intercompany_redemptions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
