<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SecurityEngineeringSupplyChainIntegrityService;
use Tests\TestCase;

class SecurityEngineeringSupplyChainIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected SecurityEngineeringSupplyChainIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SecurityEngineeringSupplyChainIntegrityService::class);
    }

    public function test_artifact_integrity_verification_and_threat_model_signoff(): void
    {
        // 430.1 Verified artifact with matching sha256 checksum
        $sha = hash('sha256', 'trusted_binary_payload');
        $art = $this->service->verifyArtifactIntegrity(
            artifactName: 'payment-sec-crypto-lib',
            version: '2.4.0',
            expectedSha256: $sha,
            actualSha256: $sha,
            vulnScanPassed: true,
            licenseCompliant: true
        );

        $this->assertEquals('payment-sec-crypto-lib', $art->artifact_name);
        $this->assertTrue((bool) $art->checksum_verified);

        // 430.3 & 430.4 Threat model for energy / finance domain
        $model = $this->service->createThreatModel(
            modelCode: 'THREAT-FIN-WALLET-01',
            domainName: 'finance',
            isHighImpact: true
        );

        $this->assertEquals('THREAT-FIN-WALLET-01', $model->model_code);
        $this->assertFalse((bool) $model->security_sign_off_completed);

        // Sign off threat model
        $signed = $this->service->signOffThreatModel('THREAT-FIN-WALLET-01', 'Chief Information Security Officer');
        $this->assertTrue((bool) $signed->security_sign_off_completed);

        // Authorize domain go-live
        $auth = $this->service->authorizeDomainGoLive('THREAT-FIN-WALLET-01');
        $this->assertEquals('GO_LIVE_AUTHORIZED', $auth['status']);

        // 430.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['checksum_failures']);
    }

    public function test_tampered_artifact_and_unsigned_threat_model_blocked_edge_cases(): void
    {
        // 430.1 & 430.4 Tampered artifact checksum mismatch is blocked
        try {
            $this->service->verifyArtifactIntegrity(
                artifactName: 'compromised-lib',
                version: '1.0.0',
                expectedSha256: 'valid_sha256_hash_value',
                actualSha256: 'tampered_bad_sha256_hash_value' // Mismatch!
            );
            $this->fail('Expected exception for tampered artifact');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Tampered artifact detected', $e->getMessage());
        }

        // 430.5 Edge case: Unsigned threat model blocks go-live for high-impact domain
        $this->service->createThreatModel('THREAT-HEALTH-ICU', 'health', true);

        try {
            $this->service->authorizeDomainGoLive('THREAT-HEALTH-ICU');
            $this->fail('Expected exception for unsigned threat model');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Security threat modeling sign-off required', $e->getMessage());
        }
    }
}
