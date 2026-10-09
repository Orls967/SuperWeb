<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseOperatingModelReadinessService;
use Tests\TestCase;

class EnterpriseOperatingModelReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseOperatingModelReadinessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseOperatingModelReadinessService::class);
    }

    public function test_raci_definition_and_change_initiative_benefit_flow(): void
    {
        // 477.1 Register complete RACI matrix for cross-line billing
        $raci = $this->service->registerProcessRaci(
            processCode: 'PRC-BILLING-RECON',
            lineCode: 'LINE-CROSS-FINANCE',
            responsible: 'Senior Billing Operations Analyst',
            accountable: 'Head of Shared Service Center',
            consulted: 'Tax & Compliance Lead',
            informed: 'Group CFO'
        );

        $this->assertEquals('PRC-BILLING-RECON', $raci->process_code);
        $this->assertEquals('Head of Shared Service Center', $raci->accountable);

        // 477.3 Propose and realize change initiative
        $init = $this->service->proposeInitiative(
            code: 'INIT-AI-OCR-INVOICING',
            title: 'Automated Supplier Invoice OCR Engine',
            capacityFte: 4,
            benefit: 1200000000.00,
            capacityAvailable: true
        );

        $this->assertEquals('approved', $init->status);
        $this->assertTrue((bool) $init->capacity_check_passed);

        // 477.4 Realize benefit
        $realized = $this->service->realizeInitiativeBenefit('INIT-AI-OCR-INVOICING');
        $this->assertEquals('benefit_realized', $realized->status);
        $this->assertTrue((bool) $realized->benefit_realized);

        // 477.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_raci_accountable_and_overcommitted_capacity_blocked_edge_cases(): void
    {
        // 477.5 Edge case: Missing accountable owner in RACI is strictly blocked
        try {
            $this->service->registerProcessRaci('PRC-ORPHAN', 'LINE-A', 'Analyst', '', 'Lead', 'Manager');
            $this->fail('Expected exception for missing RACI accountable owner');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must have an explicit single Accountable owner', $e->getMessage());
        }

        // 477.6 Risk: Overcommitted capacity blocks initiative proposal
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Team capacity check failed (overcommitted FTE limit)');

        $this->service->proposeInitiative('INIT-OVERLOAD', 'Overloaded Transformation', 20, 5000000.00, false);
    }
}
