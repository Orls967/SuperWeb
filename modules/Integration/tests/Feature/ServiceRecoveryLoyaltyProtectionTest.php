<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ServiceRecoveryLoyaltyProtectionService;
use Tests\TestCase;

class ServiceRecoveryLoyaltyProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceRecoveryLoyaltyProtectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ServiceRecoveryLoyaltyProtectionService::class);
    }

    public function test_remedy_within_authority_matrix_flow(): void
    {
        // 418.1 & 418.2 Agent role issues proactive goodwill remedy within 100k limit
        $remedy = $this->service->issueRemedy(
            remedyCode: 'REM-PROACTIVE-001',
            failureType: 'flight_delay',
            customerId: 'CUST-5512',
            remedyType: 'goodwill_points',
            remedyValue: 75000.00,
            agentRole: 'agent',
            isProactive: true
        );

        $this->assertEquals('REM-PROACTIVE-001', $remedy->remedy_code);
        $this->assertTrue((bool) $remedy->is_proactive);

        // Supervisor issues 500k voucher (within 1M limit)
        $supRemedy = $this->service->issueRemedy(
            remedyCode: 'REM-HOTEL-002',
            failureType: 'room_ac_malfunction',
            customerId: 'CUST-5513',
            remedyType: 'complimentary_voucher',
            remedyValue: 500000.00,
            agentRole: 'supervisor'
        );

        $this->assertEquals('REM-HOTEL-002', $supRemedy->remedy_code);

        // 418.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_authority_breach_and_abuse_detection_edge_cases(): void
    {
        // 418.6 Risk: Agent attempts to authorize 500k remedy (exceeding 100k limit)
        try {
            $this->service->issueRemedy(
                remedyCode: 'REM-EXCEED-LIMIT',
                failureType: 'lost_luggage',
                customerId: 'CUST-9901',
                remedyType: 'cash_refund',
                remedyValue: 500000.00,
                agentRole: 'agent' // limit is 100k!
            );
            $this->fail('Expected exception for authority limit breach');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('can only authorize remedies up to 100000', $e->getMessage());
        }

        // 418.5 Edge case: Abuse detection on repeated claims
        $this->service->issueRemedy('REM-1', 'food_cold', 'CUST-ABUSER', 'goodwill_points', 50000, 'agent');
        $this->service->issueRemedy('REM-2', 'food_cold', 'CUST-ABUSER', 'goodwill_points', 50000, 'agent');
        $this->service->issueRemedy('REM-3', 'food_cold', 'CUST-ABUSER', 'goodwill_points', 50000, 'agent');

        // 4th claim in same week triggers abuse blocker
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Abuse detected: Customer \'CUST-ABUSER\' has exceeded the weekly service recovery threshold');

        $this->service->issueRemedy('REM-4', 'food_cold', 'CUST-ABUSER', 'goodwill_points', 50000, 'agent');
    }
}
