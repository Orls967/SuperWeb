<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GroupFinanceOperatingModelService;
use Tests\TestCase;

class GroupFinanceOperatingModelTest extends TestCase
{
    use RefreshDatabase;

    protected GroupFinanceOperatingModelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GroupFinanceOperatingModelService::class);
    }

    public function test_service_catalog_and_close_orchestration_flow(): void
    {
        // 436.1 Register finance shared service catalog
        $catalog = $this->service->registerCatalogItem(
            catalogCode: 'CAT-FIN-SHARED-AP',
            serviceType: 'shared_services',
            entityCode: 'ENT-CORP-ID',
            slaHours: 24,
            costAllocationRate: 15000000.00
        );

        $this->assertEquals('CAT-FIN-SHARED-AP', $catalog->catalog_code);

        // 436.2 Close orchestration tasks with dependency
        $task1 = $this->service->createCloseTask('TSK-SUBLEDGER-CLOSE', '2026-M09', 'ENT-CORP-ID', null);
        $task2 = $this->service->createCloseTask('TSK-GL-CONSOLIDATION', '2026-M09', 'ENT-CORP-ID', 'TSK-SUBLEDGER-CLOSE');

        // Complete task1
        $c1 = $this->service->completeCloseTask('TSK-SUBLEDGER-CLOSE', true);
        $this->assertEquals('completed', $c1->status);

        // Complete task2 after prerequisite done
        $c2 = $this->service->completeCloseTask('TSK-GL-CONSOLIDATION', true);
        $this->assertEquals('completed', $c2->status);

        // 436.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_dependency_block_and_exception_escalation_edge_cases(): void
    {
        $this->service->createCloseTask('TSK-TAX-PROVISION', '2026-M09', 'ENT-CORP-ID', null);
        $this->service->createCloseTask('TSK-GROUP-REPORTING', '2026-M09', 'ENT-CORP-ID', 'TSK-TAX-PROVISION');

        // 436.2 Attempting to complete dependent task before prereq completes is blocked
        try {
            $this->service->completeCloseTask('TSK-GROUP-REPORTING', true);
            $this->fail('Expected exception for uncompleted prerequisite');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Prerequisite task \'TSK-TAX-PROVISION\' has not completed', $e->getMessage());
        }

        // 436.5 Edge case: Close task check fails -> routes exception & escalates
        try {
            $this->service->completeCloseTask('TSK-TAX-PROVISION', false);
            $this->fail('Expected exception for failed automated checks');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('routed to CONTROLLING_ESCALATION_DESK', $e->getMessage());
        }
    }
}
