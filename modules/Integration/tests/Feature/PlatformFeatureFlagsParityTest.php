<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PlatformFeatureFlagsParityService;
use Tests\TestCase;

class PlatformFeatureFlagsParityTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformFeatureFlagsParityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformFeatureFlagsParityService::class);
    }

    public function test_typed_configuration_validation_and_secrets_vault_reference(): void
    {
        // 1. Valid vault secret reference succeeds (296.1 & 296.4)
        $validSecret = $this->service->setConfiguration(
            configKey: 'DB_CONNECTION_CREDENTIALS',
            environment: 'PROD',
            dataType: 'SECRET_REF',
            value: 'vault://aws-secrets/prod/aurora/master-key',
            isSecret: true
        );
        $this->assertTrue((bool) $validSecret->is_secret_reference_only);

        // 2. Raw plaintext secret rejected (296.4 & 296.8)
        try {
            $this->service->setConfiguration('STRIPE_KEY', 'PROD', 'SECRET_REF', 'sk_live_plaintext_unencrypted_123', true);
            $this->fail('Expected exception for plaintext secret reference');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Raw secrets are prohibited', $e->getMessage());
        }

        // 3. Schema invalid type fails fast (296.1 & 296.5)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('violates schema type');
        $this->service->setConfiguration('MAX_RETRIES', 'PROD', 'INTEGER', 'NOT_AN_INTEGER');
    }

    public function test_feature_flag_scoping_and_stale_flag_auto_purge(): void
    {
        // 1. Register active regional feature flag expiring tomorrow (296.2 & 296.5)
        $this->service->registerFeatureFlag(
            flagKey: 'FLAG-EXPEDITION-DISPATCH-V2',
            ownerLeadId: 'LEAD_LOGISTICS_ANDI',
            scope: 'REGION_EAST',
            expiryDeadline: now()->addDay()->toDateString(),
            isEnabled: true
        );

        // Active for targeted scope, inactive for others (296.5)
        $isActiveEast = $this->service->isFlagActiveForScope('FLAG-EXPEDITION-DISPATCH-V2', 'REGION_EAST');
        $isActiveWest = $this->service->isFlagActiveForScope('FLAG-EXPEDITION-DISPATCH-V2', 'REGION_WEST');
        $this->assertTrue($isActiveEast);
        $this->assertFalse($isActiveWest);

        // 2. Stale flag past adoption deadline automatically purged & deactivated (296.2 & 296.6 Edge Case)
        $this->service->registerFeatureFlag(
            flagKey: 'FLAG-OLD-LEGACY-CHECKOUT',
            ownerLeadId: 'LEAD_CHECKOUT',
            scope: 'GLOBAL',
            expiryDeadline: now()->subDays(5)->toDateString(),
            isEnabled: true
        );

        $isActiveOld = $this->service->isFlagActiveForScope('FLAG-OLD-LEGACY-CHECKOUT', 'GLOBAL');
        $this->assertFalse($isActiveOld);

        $purgedRecord = DB::table('platform_feature_flags')->where('flag_key', 'FLAG-OLD-LEGACY-CHECKOUT')->first();
        $this->assertTrue((bool) $purgedRecord->is_stale_purged);
        $this->assertFalse((bool) $purgedRecord->is_enabled);
    }

    public function test_environment_drift_detection_blocks_staging_promotion(): void
    {
        // Drift detected blocks promotion gate (296.3 & 296.7)
        $drift = $this->service->detectEnvironmentDrift(
            detectionCode: 'DRIFT-STAGING-SCHEMA-2026-01',
            targetEnvironment: 'STAGING',
            component: 'SCHEMA',
            hasDrift: true
        );

        $this->assertTrue((bool) $drift->drift_detected);
        $this->assertTrue((bool) $drift->staging_promotion_gate_blocked);
    }

    public function test_platform_parity_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->setConfiguration('TIMEOUT', 'PROD', 'INTEGER', '30');
        $this->service->registerFeatureFlag('F-AUD', 'OWNER', 'GLOBAL', now()->addYear()->toDateString(), true);
        $this->service->detectEnvironmentDrift('DRIFT-AUD', 'STAGING', 'CONFIG', false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unblocked environment drift
        DB::table('platform_environment_drifts')->insert([
            'drift_detection_code' => 'DRIFT-UNBLOCKED-DISCREPANCY',
            'target_environment' => 'STAGING',
            'drift_component' => 'QUEUES',
            'drift_detected' => true,
            'staging_promotion_gate_blocked' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
