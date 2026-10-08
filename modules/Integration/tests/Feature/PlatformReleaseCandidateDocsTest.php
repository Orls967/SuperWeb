<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PlatformReleaseCandidateDocsService;
use Tests\TestCase;

class PlatformReleaseCandidateDocsTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformReleaseCandidateDocsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformReleaseCandidateDocsService::class);
    }

    public function test_documentation_drift_audit_marks_and_blocks_unimplemented_features(): void
    {
        // 1. Feature verified in codebase passes drift check (299.4 & 299.6)
        $cleanDoc = $this->service->auditDocumentationDrift('DRIFT-DOC-PAYMENTS', 'CORE_PAYMENT_MULTI_CURRENCY', true);
        $this->assertFalse((bool) $cleanDoc->drift_detected);

        // 2. Documented feature missing in active code triggers drift alert and blocks (299.6 Edge Case)
        try {
            $this->service->auditDocumentationDrift('DRIFT-DOC-PHANTOM', 'QUANTUM_ENCRYPTION_MODULE', false);
            $this->fail('Expected exception for documentation drift');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Documentation drift detected: Documented feature', $e->getMessage());
        }
    }

    public function test_release_candidate_checklist_gate(): void
    {
        // 1. Incomplete checklist fails RC gate (299.6 & 299.8)
        try {
            $this->service->evaluateReleaseCandidate(
                rcVersion: 'v300.0.0-RC1',
                gitCommitSha: '3e313a8',
                allAuditsHealthy: true,
                securityCleared: true,
                runbooksVerified: false, // Incomplete!
                apiDocsDriftClean: true
            );
            $this->fail('Expected exception for incomplete release candidate checklist');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Release candidate rejected: Incomplete RC checklist gates', $e->getMessage());
        }

        // 2. Fully satisfied checklist accepts Release Candidate (299.6, 299.7, 299.8)
        $rc = $this->service->evaluateReleaseCandidate(
            rcVersion: 'v300.0.0-RC2',
            gitCommitSha: '3e313a8',
            allAuditsHealthy: true,
            securityCleared: true,
            runbooksVerified: true,
            apiDocsDriftClean: true
        );
        $this->assertTrue((bool) $rc->is_rc_accepted);
        $this->assertEquals('V300.0.0-RC2', $rc->rc_version);
    }

    public function test_platform_release_candidate_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->auditDocumentationDrift('DRIFT-AUD', 'FEAT-1', true);
        $this->service->evaluateReleaseCandidate('v300.0.0-AUD', 'SHA', true, true, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: documentation drift detected
        DB::table('platform_documentation_drift_audits')->insert([
            'audit_code' => 'DRIFT-UNRESOLVED',
            'documented_feature_key' => 'PHANTOM_FEATURE',
            'codebase_implementation_verified' => false,
            'drift_detected' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
