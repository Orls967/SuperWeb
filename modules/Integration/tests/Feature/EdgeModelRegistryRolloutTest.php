<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\EdgeModelRegistryRolloutService;
use Tests\TestCase;

class EdgeModelRegistryRolloutTest extends TestCase
{
    use RefreshDatabase;

    protected EdgeModelRegistryRolloutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EdgeModelRegistryRolloutService::class);
    }

    public function test_staged_rollout_automatic_rollback_on_incompatibility_edge_case(): void
    {
        // Register online edge device
        $this->service->registerDevice(
            deviceCode: 'DEV-JETSON-SMELTER-01',
            siteName: 'SMELTER',
            hardwareClass: 'ARM64_JETSON',
            modelVersion: '1.0.0',
            isOnline: true
        );

        // 1. Incompatible rollout triggers automatic rollback to prior version (355.4 & 355.5 Edge Case)
        $rolloutIncompat = $this->service->executeDeviceRollout(
            rolloutCode: 'ROLLOUT-SMELTER-INCOMPAT',
            deviceCode: 'DEV-JETSON-SMELTER-01',
            targetVersion: '2.0.0-CUDA12',
            isCompatible: false // Incompatible with Jetson!
        );
        $this->assertFalse((bool) $rolloutIncompat->is_hardware_compatible);
        $this->assertTrue((bool) $rolloutIncompat->automatic_rollback_executed);

        // Verify device stays on prior version
        $dev = DB::table('edge_model_registry_devices')->where('device_code', 'DEV-JETSON-SMELTER-01')->first();
        $this->assertEquals('1.0.0', $dev->installed_model_version);

        // 2. Compatible rollout succeeds (355.2 & 355.4)
        $rolloutCompat = $this->service->executeDeviceRollout(
            rolloutCode: 'ROLLOUT-SMELTER-COMPAT',
            deviceCode: 'DEV-JETSON-SMELTER-01',
            targetVersion: '1.1.0-JETPACK',
            isCompatible: true
        );
        $this->assertTrue((bool) $rolloutCompat->is_hardware_compatible);
        $this->assertFalse((bool) $rolloutCompat->automatic_rollback_executed);

        $devUpdated = DB::table('edge_model_registry_devices')->where('device_code', 'DEV-JETSON-SMELTER-01')->first();
        $this->assertEquals('1.1.0-JETPACK', $devUpdated->installed_model_version);
    }

    public function test_offline_device_queues_update_safely(): void
    {
        // Register offline device (355.4 & 355.6 Risk)
        $this->service->registerDevice(
            deviceCode: 'DEV-OFFLINE-HAUL-09',
            siteName: 'MINE',
            hardwareClass: 'X86_EDGE_SERVER',
            modelVersion: '1.0.0',
            isOnline: false // Offline!
        );

        $queued = $this->service->executeDeviceRollout(
            rolloutCode: 'ROLLOUT-OFFLINE-01',
            deviceCode: 'DEV-OFFLINE-HAUL-09',
            targetVersion: '1.2.0',
            isCompatible: true
        );
        $this->assertTrue((bool) $queued->update_queued_offline);
        $this->assertFalse((bool) $queued->automatic_rollback_executed);
    }

    public function test_edge_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerDevice('DEV-AUD', 'MINE', 'X86', '1.0', true);
        $this->service->executeDeviceRollout('R-AUD', 'DEV-AUD', '1.1', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: incompatible rollout without automatic rollback
        DB::table('edge_device_staged_rollouts')->insert([
            'rollout_code' => 'R-DEFECT-UNROLLED',
            'device_code' => 'DEV-AUD',
            'target_model_version' => '9.9',
            'prior_model_version' => '1.0',
            'is_hardware_compatible' => false,
            'automatic_rollback_executed' => false, // Discrepancy!
            'update_queued_offline' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
