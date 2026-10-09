<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseMasterDataGovernanceService;
use Tests\TestCase;

class EnterpriseMasterDataGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseMasterDataGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseMasterDataGovernanceService::class);
    }

    public function test_golden_record_merge_reversibility_and_propagation_flow(): void
    {
        // 456.1 Create Golden Records for Vendor Domain
        $rec1 = $this->service->createGoldenRecord('vendor', 'VND-MASTER-A', ['name' => 'PT Sumber Logistik', 'tax_id' => '01.234.567.8-012.000']);
        $rec2 = $this->service->createGoldenRecord('vendor', 'VND-MASTER-B', ['name' => 'PT Sumber Logistik Indonesia', 'tax_id' => '01.234.567.8-012.000']);

        $this->assertEquals('VND-MASTER-A', $rec1->golden_id);
        $this->assertFalse((bool) $rec1->is_merged);

        // 456.2 & 456.5 Merge record B into record A (duplicate detection result)
        $merged = $this->service->mergeGoldenRecords('VND-MASTER-B', 'VND-MASTER-A');
        $this->assertTrue((bool) $merged->is_merged);
        $this->assertEquals('VND-MASTER-A', $merged->merged_into_golden_id);
        $this->assertNotNull($merged->pre_merge_backup_state);

        // 456.5 Test reversibility: Reverse merge restoring record B
        $reversed = $this->service->reverseMerge('VND-MASTER-B');
        $this->assertFalse((bool) $reversed->is_merged);
        $this->assertNull($reversed->merged_into_golden_id);

        // 456.2 & 456.4 Queue and complete downstream propagation
        $prop = $this->service->queuePropagation('VND-MASTER-A', 'ERP_SAP');
        $this->assertFalse((bool) $prop->is_propagated);

        $completed = $this->service->completePropagation($prop->propagation_code, true);
        $this->assertTrue((bool) $completed->is_propagated);

        // 456.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_propagation_failure_retry_and_already_merged_blocked_edge_cases(): void
    {
        $this->service->createGoldenRecord('customer', 'CUST-01', ['name' => 'Customer 1']);
        $this->service->createGoldenRecord('customer', 'CUST-02', ['name' => 'Customer 2']);
        $this->service->mergeGoldenRecords('CUST-02', 'CUST-01');

        // Cannot merge already merged record
        try {
            $this->service->mergeGoldenRecords('CUST-02', 'CUST-01');
            $this->fail('Expected exception for merging already merged record');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('is already merged', $e->getMessage());
        }

        // 456.6 Risk: Downstream propagation failure increments retry count and alerts
        $prop = $this->service->queuePropagation('CUST-01', 'BILLING_SYSTEM');

        try {
            $this->service->completePropagation($prop->propagation_code, false);
            $this->fail('Expected exception for failed propagation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Retry queued', $e->getMessage());
        }
    }
}
