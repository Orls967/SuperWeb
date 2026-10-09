<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AssetAvailabilityProgramService;
use Tests\TestCase;

class AssetAvailabilityProgramTest extends TestCase
{
    use RefreshDatabase;

    protected AssetAvailabilityProgramService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssetAvailabilityProgramService::class);
    }

    public function test_pool_registration_and_normal_allocation(): void
    {
        // 410.1 & 410.2 Register asset pool
        $pool = $this->service->registerAssetPool(
            assetClass: 'LOGISTICS_TRUCK',
            totalUnits: 100,
            maintenanceReserveUnits: 15,
            bufferCapacityUnits: 10,
            isCritical: true
        );

        $this->assertEquals('LOGISTICS_TRUCK', $pool->asset_class);
        $this->assertEquals(100, $pool->total_units);

        // Allocate 50 units (usable = 100 - 15 = 85)
        $allocated = $this->service->allocateAssets('LOGISTICS_TRUCK', 50);
        $this->assertEquals(50, $allocated->allocated_units);

        // 410.3 Shortage escalation with approved substitution
        $esc = $this->service->escalateShortage(
            escalationCode: 'ESC-2026-001',
            assetClass: 'LOGISTICS_TRUCK',
            resolutionType: 'substitute',
            substituteClass: 'HEAVY_VAN',
            qualityGateApproved: true,
            cost: 5000000.00,
            approvedBy: 'Fleet Ops Director'
        );

        $this->assertEquals('ESC-2026-001', $esc->escalation_code);

        // 410.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_maintenance_reserve_breach_strictly_blocked_edge_case(): void
    {
        // 410.4 & 410.5 Edge case: Maintenance reserve cannot be violated
        $this->service->registerAssetPool(
            assetClass: 'HOSPITAL_ICU_BED',
            totalUnits: 20,
            maintenanceReserveUnits: 5,
            bufferCapacityUnits: 3,
            isCritical: true
        );

        // Attempting to allocate 16 units (usable is only 20 - 5 = 15)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('violates mandatory maintenance reserve');

        $this->service->allocateAssets('HOSPITAL_ICU_BED', 16);
    }
}
