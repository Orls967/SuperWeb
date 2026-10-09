<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\BusinessEthicsAntiCorruptionService;
use Tests\TestCase;

class BusinessEthicsAntiCorruptionTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessEthicsAntiCorruptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BusinessEthicsAntiCorruptionService::class);
    }

    public function test_gift_threshold_enforcement_and_compliance_pre_approval(): void
    {
        // 1. Gift under threshold ($50 <= $100) passes without pre-approval (340.2 & 340.4)
        $smallGift = $this->service->registerGiftHospitality(
            giftCode: 'GIFT-CALENDAR-01',
            employeeId: 'EMP-PROCUREMENT-101',
            counterparty: 'PT Supplier Sejahtera',
            valueUsd: 50.0,
            preApproved: false,
            thresholdUsd: 100.0
        );
        $this->assertTrue((bool) $smallGift->gift_accepted_or_given);

        // 2. Gift exceeding threshold ($250 > $100) without pre-approval throws exception (340.4)
        try {
            $this->service->registerGiftHospitality(
                giftCode: 'GIFT-GOLF-UNAPPROVED',
                employeeId: 'EMP-PROCUREMENT-101',
                counterparty: 'PT Supplier Sejahtera',
                valueUsd: 250.0,
                preApproved: false, // Not pre-approved!
                thresholdUsd: 100.0
            );
            $this->fail('Expected exception for unapproved gift over threshold');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Gift exceeding policy threshold ($100) requires compliance pre-approval', $e->getMessage());
        }

        // 3. Gift exceeding threshold with pre-approval succeeds (340.2)
        $approvedGift = $this->service->registerGiftHospitality(
            giftCode: 'GIFT-GOLF-APPROVED',
            employeeId: 'EMP-PROCUREMENT-101',
            counterparty: 'PT Supplier Sejahtera',
            valueUsd: 250.0,
            preApproved: true,
            thresholdUsd: 100.0
        );
        $this->assertTrue((bool) $approvedGift->pre_approved_by_compliance);
    }

    public function test_intermediary_payment_rationale_and_bribery_detection_edge_case(): void
    {
        // 1. Intermediary payment without economic rationale throws exception (340.4 & 340.6)
        try {
            $this->service->processIntermediaryPayment(
                paymentCode: 'PAY-NO-RATIONALE',
                agentId: 'AGT-CONSULTING-LTD',
                feeAmountUsd: 50000.0,
                hasEconomicRationale: false, // No rationale!
                briberySuspected: false
            );
            $this->fail('Expected exception for payment without economic rationale');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Payment requires documented economic rationale and performance proof', $e->getMessage());
        }

        // 2. Intermediary payment with economic rationale succeeds (340.3 & 340.4)
        $validPayment = $this->service->processIntermediaryPayment(
            paymentCode: 'PAY-VALID-COMMISSION-01',
            agentId: 'AGT-SHIPPING-BROKER',
            feeAmountUsd: 15000.0,
            hasEconomicRationale: true,
            briberySuspected: false
        );
        $this->assertTrue((bool) $validPayment->payment_cleared);
        $this->assertFalse((bool) $validPayment->bribery_collusion_flagged);

        // 3. Suspected bribery triggers independent investigation and blocks clearance (340.5 Edge Case)
        $briberyPayment = $this->service->processIntermediaryPayment(
            paymentCode: 'PAY-BRIBERY-FLAGGED-02',
            agentId: 'AGT-CUSTOMS-EXPEDITER',
            feeAmountUsd: 30000.0,
            hasEconomicRationale: true,
            briberySuspected: true // Bribery flagged!
        );
        $this->assertTrue((bool) $briberyPayment->bribery_collusion_flagged);
        $this->assertTrue((bool) $briberyPayment->independent_investigation_ordered);
        $this->assertFalse((bool) $briberyPayment->payment_cleared);
    }

    public function test_ethics_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerGiftHospitality('G-AUD', 'E1', 'C1', 50.0, false, 100.0);
        $this->service->processIntermediaryPayment('P-AUD', 'A1', 1000.0, true, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: bribery flagged payment cleared
        DB::table('commercial_intermediary_payments')->insert([
            'payment_code' => 'PAY-DEFECT-CLEARED-BRIBE',
            'agent_id' => 'A2',
            'fee_amount_usd' => 20000.0,
            'has_documented_economic_rationale' => true,
            'bribery_collusion_flagged' => true,
            'independent_investigation_ordered' => true,
            'payment_cleared' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
