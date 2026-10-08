<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CustomerLoyaltySubscriptionEconomyService;
use Tests\TestCase;

class CustomerLoyaltySubscriptionEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerLoyaltySubscriptionEconomyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerLoyaltySubscriptionEconomyService::class);
    }

    public function test_customer_consent_and_unified_policy_conflict_resolution_edge_case(): void
    {
        // 1. Granting cross-line entitlement without customer consent fails (378.1 & 378.4)
        try {
            $this->service->grantCrossLineEntitlement(
                entitlementCode: 'ENT-VIP-CLUB-001',
                customerId: 'CUST-LOYAL-1001',
                customerConsentActive: false, // No consent!
                singlePolicyRuleId: 'GROUP-POLICY-RULE-GLOBAL-PASS'
            );
            $this->fail('Expected exception for unconsented entitlement');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has not granted consent for cross-line entitlements', $e->getMessage());
        }

        // 2. Conflicting line policy rules fail (378.5 Edge Case)
        try {
            $this->service->grantCrossLineEntitlement(
                entitlementCode: 'ENT-VIP-CLUB-002',
                customerId: 'CUST-LOYAL-1001',
                customerConsentActive: true,
                singlePolicyRuleId: 'CONFLICTING-LINE-SPECIFIC-RULE'
            );
            $this->fail('Expected exception for conflicting policy rules');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Entitlement requires resolution via single unified group policy', $e->getMessage());
        }

        // 3. Consented entitlement with unified group policy succeeds (378.1 & 378.5)
        $entitlement = $this->service->grantCrossLineEntitlement(
            entitlementCode: 'ENT-VIP-CLUB-003',
            customerId: 'CUST-LOYAL-1001',
            customerConsentActive: true,
            singlePolicyRuleId: 'GROUP-POLICY-RULE-GLOBAL-PASS'
        );
        $this->assertTrue((bool) $entitlement->customer_consent_active);
        $this->assertTrue((bool) $entitlement->conflict_resolved);
    }

    public function test_intercompany_redemption_split_balancing(): void
    {
        // 1. Unbalanced split fails (378.2 & 378.4)
        try {
            $this->service->settleIntercompanyRedemption(
                redemptionCode: 'RDM-SPLIT-001',
                customerId: 'CUST-LOYAL-1001',
                grossAmount: 100.00,
                entityAShare: 40.00,
                entityBShare: 50.00 // Sum = 90 != 100!
            );
            $this->fail('Expected exception for unbalanced intercompany redemption split');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Sum of entity shares ($90) does not match gross redemption amount ($100)', $e->getMessage());
        }

        // 2. Exactly balanced split succeeds (378.2 & 378.4)
        $settled = $this->service->settleIntercompanyRedemption(
            redemptionCode: 'RDM-SPLIT-002',
            customerId: 'CUST-LOYAL-1001',
            grossAmount: 100.00,
            entityAShare: 60.00,
            entityBShare: 40.00 // Sum = 100 == 100!
        );
        $this->assertEquals(100.00, $settled->gross_redemption_amount);
        $this->assertTrue((bool) $settled->intercompany_balanced);
    }

    public function test_crm_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->grantCrossLineEntitlement('E-AUD', 'C1', true, 'POLICY-1');
        $this->service->settleIntercompanyRedemption('R-AUD', 'C1', 10.0, 5.0, 5.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unconsented entitlement
        DB::table('global_customer_cross_line_entitlements')->insert([
            'entitlement_code' => 'E-DEFECT-UNCONSENTED',
            'customer_id' => 'C1',
            'customer_consent_active' => false, // Discrepancy!
            'primary_policy_rule_id' => 'POLICY-1',
            'conflict_resolved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
