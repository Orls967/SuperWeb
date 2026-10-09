<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ProductLifecycleCircularDesignService;
use Tests\TestCase;

class ProductLifecycleCircularDesignTest extends TestCase
{
    use RefreshDatabase;

    protected ProductLifecycleCircularDesignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductLifecycleCircularDesignService::class);
    }

    public function test_lca_assessment_versioning_and_precision_labeling(): void
    {
        // 1. Complete LCA data is verified precise (328.1 & 328.4)
        $preciseLca = $this->service->conductLcaAssessment(
            assessmentCode: 'LCA-EV-BATTERY-PACK-V1',
            sku: 'SKU-BATTERY-PACK-75KWH',
            version: 'v1.0.0',
            emissionsKgCo2e: 4200.0,
            hasCompleteData: true,
            ecoApproved: true
        );
        $this->assertEquals('PRECISE_VERIFIED', $preciseLca->precision_tier);

        // 2. Incomplete LCA data must strictly be labeled ESTIMATED_INCOMPLETE (328.5 Edge Case)
        $estimatedLca = $this->service->conductLcaAssessment(
            assessmentCode: 'LCA-EV-BATTERY-PACK-V2-EST',
            sku: 'SKU-BATTERY-PACK-75KWH',
            version: 'v2.0.0-PROTOTYPE',
            emissionsKgCo2e: 3600.0,
            hasCompleteData: false, // Incomplete!
            ecoApproved: true
        );
        $this->assertEquals('ESTIMATED_INCOMPLETE', $estimatedLca->precision_tier);
    }

    public function test_circular_take_back_mass_balance_reconciliation(): void
    {
        // 1. Reconciled mass balance: 1,000kg intake = 600kg repaired + 350kg recycled + 50kg waste (328.3 & 328.4)
        $batch = $this->service->processTakeBackBatch(
            batchCode: 'TB-NICKEL-TIRES-01',
            sku: 'SKU-HAUL-TRUCK-TIRE-40R57',
            intakeMassKg: 1000.0,
            repairedMassKg: 600.0,
            recycledMassKg: 350.0,
            wasteMassKg: 50.0
        );
        $this->assertTrue((bool) $batch->mass_balance_reconciled);
        $this->assertEquals(95.0, (float) $batch->recovery_yield_pct);

        // 2. Unreconciled mass balance throws exception (328.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mass balance reconciliation breach: Sum of repaired');
        $this->service->processTakeBackBatch(
            batchCode: 'TB-MISMATCH',
            sku: 'SKU-HAUL-TRUCK-TIRE-40R57',
            intakeMassKg: 1000.0,
            repairedMassKg: 400.0,
            recycledMassKg: 300.0,
            wasteMassKg: 50.0 // 750kg != 1000kg!
        );
    }

    public function test_esg_circularity_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->conductLcaAssessment('LCA-AUD', 'SKU-1', 'v1', 100.0, true, true);
        $this->service->processTakeBackBatch('TB-AUD', 'SKU-1', 100.0, 50.0, 40.0, 10.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved high-emission LCA
        DB::table('product_lifecycle_carbon_assessments')->insert([
            'lca_assessment_code' => 'LCA-DEFECT-UNAPPROVED',
            'product_sku' => 'SKU-1',
            'product_version' => 'v3',
            'lifecycle_emissions_kg_co2e' => 5000.0,
            'precision_tier' => 'PRECISE_VERIFIED',
            'eco_engineering_approved' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
