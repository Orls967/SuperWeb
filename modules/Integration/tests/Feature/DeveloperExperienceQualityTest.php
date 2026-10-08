<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\DeveloperExperienceQualityService;
use Tests\TestCase;

class DeveloperExperienceQualityTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperExperienceQualityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DeveloperExperienceQualityService::class);
    }

    public function test_provision_sandbox_environment(): void
    {
        $sandbox = $this->service->provisionSandbox(
            developerId: 'DEV-ENGINEER-42',
            environmentType: 'SANDBOX',
            domainSlice: 'FINTECH'
        );

        $this->assertNotNull($sandbox);
        $this->assertEquals('DEV-ENGINEER-42', $sandbox->developer_id);
        $this->assertEquals('ACTIVE', $sandbox->status);
        $this->assertEquals('FINTECH', $sandbox->domain_slice);
        $this->assertStringStartsWith('SBX-', $sandbox->sandbox_code);

        $this->assertDatabaseHas('platform_dev_sandboxes', [
            'sandbox_code' => $sandbox->sandbox_code,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_stale_sandbox_auto_reclaimed_after_7_days(): void
    {
        // 1. Fresh sandbox (< 7 days)
        $fresh = $this->service->provisionSandbox(
            developerId: 'DEV-FRESH',
            environmentType: 'EPHEMERAL',
            domainSlice: 'HEALTHCARE'
        );

        // 2. Stale sandbox (> 7 days)
        $stale = $this->service->provisionSandbox(
            developerId: 'DEV-STALE',
            environmentType: 'SANDBOX',
            domainSlice: 'MINING'
        );
        DB::table('platform_dev_sandboxes')
            ->where('id', $stale->id)
            ->update(['last_activity_at' => now()->subDays(10)]);

        $reclaimed = $this->service->reclaimStaleSandboxes(7);
        $this->assertEquals(1, $reclaimed);

        $freshRecord = DB::table('platform_dev_sandboxes')->find($fresh->id);
        $staleRecord = DB::table('platform_dev_sandboxes')->find($stale->id);

        $this->assertEquals('ACTIVE', $freshRecord->status);
        $this->assertEquals('RECLAIMED', $staleRecord->status);
    }

    public function test_ci_quality_gate_evaluation_pass_and_reject(): void
    {
        // 1. Passing gate
        $passedGate = $this->service->evaluateQualityGate(
            commitHash: 'commit-abc-123',
            lintPassed: true,
            staticAnalysisPassed: true,
            securityAuditPassed: true,
            mutationScorePct: 88.50
        );

        $this->assertEquals('PASSED', $passedGate->gate_verdict);
        $this->assertNull($passedGate->rejection_reason);

        // 2. Rejecting gate (low mutation score and lint failure)
        $rejectedGate = $this->service->evaluateQualityGate(
            commitHash: 'commit-bad-456',
            lintPassed: false,
            staticAnalysisPassed: true,
            securityAuditPassed: true,
            mutationScorePct: 65.00
        );

        $this->assertEquals('REJECTED', $rejectedGate->gate_verdict);
        $this->assertStringContainsString('Code style linting (Pint) failed', $rejectedGate->rejection_reason);
        $this->assertStringContainsString('Mutation testing score (65%) is below the mandatory 80% threshold', $rejectedGate->rejection_reason);
    }

    public function test_synthetic_data_generation_with_deterministic_masking(): void
    {
        $rawPii = 'john.doe.personal@confidential-mail.com';
        $seedKey = 'SEED-PROJECT-GAMMA';

        $seed = $this->service->generateSyntheticDataSeed(
            businessLine: 'INSURANCE',
            recordType: 'CUSTOMER_PROFILE',
            rawPiiValue: $rawPii,
            seedKey: $seedKey
        );

        $this->assertTrue((bool) $seed->is_pii_masked);
        $this->assertStringStartsWith('SEED-', $seed->seed_code);

        $decoded = json_decode($seed->masked_sample_json, true);
        $this->assertNotNull($decoded);
        $this->assertTrue($decoded['is_synthetic']);
        $this->assertStringStartsWith('SYNTH-', $decoded['synthetic_subject_id']);
        $this->assertStringStartsWith('MASKED_', $decoded['masked_pii_token']);
        // Ensure raw PII is never stored in payload
        $this->assertStringNotContainsString($rawPii, $seed->masked_sample_json);
    }

    public function test_platform_audit_healthy_and_discrepancy_detection(): void
    {
        // Setup healthy state
        $this->service->provisionSandbox('DEV-01', 'SANDBOX', 'ALL_30_LINES');
        $this->service->evaluateQualityGate('commit-clean', true, true, true, 92.0);
        $this->service->generateSyntheticDataSeed('COMMERCE', 'ORDER', 'credit-card-dummy', 'KEY-1');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
        $this->assertEquals(1, $audit['total_sandboxes']);
        $this->assertEquals(1, $audit['total_ci_gates']);
        $this->assertEquals(1, $audit['total_synthetic_seeds']);

        // Inject discrepancy: unmasked synthetic seed
        DB::table('platform_synthetic_data_seeds')->insert([
            'seed_code' => 'DIRTY-SEED',
            'business_line' => 'LEAK',
            'record_type' => 'RAW_UNMASKED',
            'is_pii_masked' => false,
            'deterministic_seed_key' => 'FAIL',
            'masked_sample_json' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
