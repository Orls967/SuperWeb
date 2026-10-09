<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SupplyChainTraceabilityService;
use Tests\TestCase;

class SupplyChainTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    protected SupplyChainTraceabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplyChainTraceabilityService::class);
    }

    public function test_traceability_gap_blocks_verified_claim_and_expiry_blocks_shipment(): void
    {
        $this->service->registerSupplier('SUP-NICKEL-01', 'PT Harita Nickel Minerals', false);

        // 1. Full provenance without trace gaps or expiry gets full verification & shipment clearance (327.1 & 327.4)
        $cleanBatch = $this->service->registerProvenanceBatch(
            batchCode: 'BATCH-NICKEL-ORE-001',
            supplierId: 'SUP-NICKEL-01',
            commodityType: 'MINERALS',
            hasTraceGap: false,
            certificateExpired: false
        );
        $this->assertTrue((bool) $cleanBatch->chain_of_custody_verified);
        $this->assertTrue((bool) $cleanBatch->shipment_cleared);

        // 2. Trace gaps block chain-of-custody verification (327.4 & 327.6 Risk)
        $gappedBatch = $this->service->registerProvenanceBatch(
            batchCode: 'BATCH-NICKEL-GAPPED',
            supplierId: 'SUP-NICKEL-01',
            commodityType: 'MINERALS',
            hasTraceGap: true,
            certificateExpired: false
        );
        $this->assertFalse((bool) $gappedBatch->chain_of_custody_verified);
        $this->assertFalse((bool) $gappedBatch->shipment_cleared);

        // 3. Expired certificate blocks shipment clearance (327.4)
        $expiredBatch = $this->service->registerProvenanceBatch(
            batchCode: 'BATCH-NICKEL-EXPIRED-CERT',
            supplierId: 'SUP-NICKEL-01',
            commodityType: 'MINERALS',
            hasTraceGap: false,
            certificateExpired: true // Expired!
        );
        $this->assertTrue((bool) $expiredBatch->chain_of_custody_verified);
        $this->assertFalse((bool) $expiredBatch->shipment_cleared);
    }

    public function test_supplier_refusing_traceability_is_suspended_and_blocks_sourcing(): void
    {
        // 1. Supplier refusing traceability is immediately suspended (327.5 Edge Case)
        $refusedSupplier = $this->service->registerSupplier(
            supplierId: 'SUP-DEFIANT-02',
            supplierName: 'Defiant Timber Logging Corp',
            refusedTraceability: true
        );
        $this->assertTrue((bool) $refusedSupplier->refused_traceability);
        $this->assertTrue((bool) $refusedSupplier->is_suspended);

        // 2. Processing batch from suspended supplier throws exception (327.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sourcing violation: Cannot source or process batch from suspended supplier');
        $this->service->registerProvenanceBatch(
            batchCode: 'BATCH-BLOCKED-PO',
            supplierId: 'SUP-DEFIANT-02',
            commodityType: 'TIMBER',
            hasTraceGap: false,
            certificateExpired: false
        );
    }

    public function test_supplier_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerSupplier('SUP-AUD', 'Audit Supplier', false);
        $this->service->registerProvenanceBatch('BATCH-AUD', 'SUP-AUD', 'PHARMA', false, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: trace gap but falsely marked verified
        DB::table('traceable_supply_chain_origins')->insert([
            'provenance_batch_code' => 'BATCH-DEFECT-UNVERIFIED',
            'supplier_id' => 'SUP-AUD',
            'commodity_type' => 'MINERALS',
            'has_trace_gap' => true,
            'chain_of_custody_verified' => true, // Discrepancy!
            'certificate_expired' => false,
            'shipment_cleared' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
