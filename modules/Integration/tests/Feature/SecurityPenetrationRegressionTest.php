<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SecurityPenetrationRegressionService;
use Tests\TestCase;

class SecurityPenetrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected SecurityPenetrationRegressionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SecurityPenetrationRegressionService::class);
    }

    public function test_critical_security_finding_blocks_release_edge_case(): void
    {
        // 1. Critical finding strictly blocks release (398.4 & 398.5 Edge Case)
        try {
            $this->service->recordPenetrationFinding(
                findingCode: 'VULN-AUTH-BYPASS-01',
                severity: 'CRITICAL'
            );
            $this->fail('Expected exception for critical security finding blocking release');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('strictly blocks release until fixed and retested', $e->getMessage());
        }

        // Verify blocked status in DB
        $finding = DB::table('global_stress_security_penetration_findings')->where('finding_code', 'VULN-AUTH-BYPASS-01')->first();
        $this->assertNotNull($finding);
        $this->assertTrue((bool) $finding->release_blocked);
        $this->assertFalse((bool) $finding->resolved_and_retested);

        // 2. Resolving and retesting unblocks release (398.5)
        $resolved = $this->service->resolveFinding('VULN-AUTH-BYPASS-01');
        $this->assertFalse((bool) $resolved->release_blocked);
        $this->assertTrue((bool) $resolved->resolved_and_retested);
    }

    public function test_privacy_scan_audit(): void
    {
        // Clean privacy scan passes (398.3 & 398.4)
        $cleanScan = $this->service->runPrivacyScan(
            auditRunCode: 'PRV-SCAN-RUN-101',
            piiDetectedInTraces: false
        );
        $this->assertTrue((bool) $cleanScan->privacy_passed);

        // Scan detecting PII fails (398.3)
        $dirtyScan = $this->service->runPrivacyScan(
            auditRunCode: 'PRV-SCAN-RUN-102',
            piiDetectedInTraces: true
        );
        $this->assertFalse((bool) $dirtyScan->privacy_passed);
    }

    public function test_security_privacy_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordPenetrationFinding('V-LOW', 'LOW');
        $this->service->runPrivacyScan('PRV-AUD', false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unblocked/unresolved critical finding
        DB::table('global_stress_security_penetration_findings')->insert([
            'finding_code' => 'V-DEFECT-UNRESOLVED-CRITICAL',
            'severity' => 'CRITICAL',
            'release_blocked' => true, // Discrepancy!
            'resolved_and_retested' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
