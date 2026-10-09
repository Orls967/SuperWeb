<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ReleaseManagementChangeAdvisoryService;
use Tests\TestCase;

class ReleaseManagementChangeAdvisoryTest extends TestCase
{
    use RefreshDatabase;

    protected ReleaseManagementChangeAdvisoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReleaseManagementChangeAdvisoryService::class);
    }

    public function test_release_train_and_normal_change_flow(): void
    {
        // 427.2 Create release train
        $train = $this->service->createReleaseTrain(
            trainCode: 'TRAIN-2026-W41',
            departureDate: '2026-10-14'
        );

        $this->assertEquals('TRAIN-2026-W41', $train->train_code);

        // 427.1 Submit normal change
        $change = $this->service->submitChangeRequest(
            changeCode: 'CHG-O2C-001',
            title: 'Upgrade Order-to-Cash Database Indexing',
            riskClass: 'normal',
            targetTrain: 'TRAIN-2026-W41'
        );

        $this->assertEquals('pending_approval', $change->status);
        $this->assertFalse((bool) $change->cab_approved);

        // CAB approves
        $approved = $this->service->approveCabChange('CHG-O2C-001');
        $this->assertEquals('approved', $approved->status);
        $this->assertTrue((bool) $approved->cab_approved);

        // 427.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_emergency_change_retrospective_and_wip_limit_edge_cases(): void
    {
        $this->service->createReleaseTrain('TRAIN-EMERGENCY', '2026-10-10');

        // 427.5 Edge case: Emergency change bypasses initial CAB for immediate deployment
        $this->service->submitChangeRequest(
            changeCode: 'CHG-HOTFIX-P0',
            title: 'Zero-day vulnerability patch in Auth gateway',
            riskClass: 'emergency',
            targetTrain: 'TRAIN-EMERGENCY'
        );

        $deployed = $this->service->deployEmergencyChange('CHG-HOTFIX-P0');
        $this->assertEquals('deployed', $deployed->status);
        $this->assertFalse((bool) $deployed->emergency_retrospective_completed);

        // Audit catches uncompleted retrospective (427.4)
        $auditPending = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditPending['status']);
        $this->assertEquals(1, $auditPending['unremediated_emergency']);

        // Complete retrospective
        $this->service->completeEmergencyRetrospective('CHG-HOTFIX-P0');

        $auditResolved = $this->service->audit();
        $this->assertEquals('HEALTHY', $auditResolved['status']);
        $this->assertEquals(0, $auditResolved['discrepancy_count']);

        // 427.6 Risk: WIP limit per train (10 features)
        $this->service->createReleaseTrain('TRAIN-TINY', '2026-10-11');
        for ($i = 1; $i <= 10; $i++) {
            $this->service->submitChangeRequest("CHG-TINY-{$i}", "Feature {$i}", 'standard', 'TRAIN-TINY');
        }

        // 11th change rejected due to WIP limit
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has reached its WIP limit of 10 features');

        $this->service->submitChangeRequest('CHG-TINY-11', 'Excess feature', 'standard', 'TRAIN-TINY');
    }
}
