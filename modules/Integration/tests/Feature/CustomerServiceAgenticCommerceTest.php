<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CustomerServiceAgenticCommerceService;
use Tests\TestCase;

class CustomerServiceAgenticCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerServiceAgenticCommerceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerServiceAgenticCommerceService::class);
    }

    public function test_customer_service_disclosure_and_escalation_handoff_edge_case(): void
    {
        // 1. Session without disclosure throws exception (353.3 & 353.6)
        try {
            $this->service->handleSession(
                sessionCode: 'SESS-UNDISCLOSED-01',
                customerId: 'CUST-001',
                disclosureProvided: false, // No disclosure!
                resolvedAutonomously: true
            );
            $this->fail('Expected exception for undisclosed AI session');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Session requires mandatory AI transparency disclosure', $e->getMessage());
        }

        // 2. Escalation without context throws exception (353.5 Edge Case)
        try {
            $this->service->handleSession(
                sessionCode: 'SESS-EMPTY-HANDOFF-02',
                customerId: 'CUST-002',
                disclosureProvided: true,
                resolvedAutonomously: false,
                handoffContext: null // Empty handoff!
            );
            $this->fail('Expected exception for empty escalation context');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Escalation requires non-empty context payload to prevent customer data loss', $e->getMessage());
        }

        // 3. Autonomous resolution succeeds (353.1 & 353.4)
        $autoSession = $this->service->handleSession(
            sessionCode: 'SESS-AUTO-RESOLVED-03',
            customerId: 'CUST-003',
            disclosureProvided: true,
            resolvedAutonomously: true
        );
        $this->assertTrue((bool) $autoSession->resolved_autonomously);
        $this->assertFalse((bool) $autoSession->escalated_to_human);

        // 4. Escalation with context handoff succeeds (353.1 & 353.5)
        $escalatedSession = $this->service->handleSession(
            sessionCode: 'SESS-ESCALATED-04',
            customerId: 'CUST-004',
            disclosureProvided: true,
            resolvedAutonomously: false,
            handoffContext: 'User disputed mining equipment delivery timeline; order #9923.'
        );
        $this->assertTrue((bool) $escalatedSession->escalated_to_human);
        $this->assertNotNull($escalatedSession->handoff_context_payload);
    }

    public function test_refund_limit_threshold_guard(): void
    {
        // 1. Refund within autonomous limit ($45 <= $100) approves autonomously (353.1 & 353.4)
        $smallRefund = $this->service->processCommerceRefund(
            refundCode: 'REF-SMALL-01',
            customerId: 'CUST-005',
            amountUsd: 45.0,
            autonomousLimitUsd: 100.0
        );
        $this->assertTrue((bool) $smallRefund->autonomous_approved);
        $this->assertFalse((bool) $smallRefund->requires_manager_review);

        // 2. Refund exceeding autonomous limit ($350 > $100) requires manager review (353.1 & 353.4)
        $largeRefund = $this->service->processCommerceRefund(
            refundCode: 'REF-LARGE-02',
            customerId: 'CUST-006',
            amountUsd: 350.0,
            autonomousLimitUsd: 100.0
        );
        $this->assertFalse((bool) $largeRefund->autonomous_approved);
        $this->assertTrue((bool) $largeRefund->requires_manager_review);
    }

    public function test_crm_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->handleSession('S-AUD', 'C1', true, true);
        $this->service->processCommerceRefund('R-AUD', 'C1', 50.0, 100.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: over-limit refund autonomously approved
        DB::table('agentic_commerce_refund_requests')->insert([
            'refund_code' => 'R-DEFECT-OVERLIMIT-AUTO',
            'customer_id' => 'C2',
            'refund_amount_usd' => 500.0,
            'autonomous_limit_usd' => 100.0,
            'autonomous_approved' => true, // Discrepancy!
            'requires_manager_review' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
