<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseDigitalTrustService;
use Tests\TestCase;

class EnterpriseDigitalTrustTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseDigitalTrustService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseDigitalTrustService::class);
    }

    public function test_verifiable_claim_and_trust_score_flow(): void
    {
        $rawEvidence = 'ISO-9001-CERTIFIED-BATCH-SERIAL-998234-VALID-2026';
        $evidenceHash = hash('sha256', $rawEvidence);

        // 484.1 Publish verifiable claim with cryptographic evidence hash
        $claim = $this->service->publishClaim(
            code: 'CLAIM-ISO9001-WORKSHOP',
            type: 'quality',
            statement: '100% Automotive Workshop Service Operations Certified to ISO 9001:2015',
            evidenceHash: $evidenceHash,
            publicVerifiable: true
        );

        $this->assertEquals('CLAIM-ISO9001-WORKSHOP', $claim->claim_code);
        $this->assertEquals('published_verified', $claim->status);

        // 484.2 Third-party verification API matches exact cryptographic proof
        $isValid = $this->service->verifyClaimHash('CLAIM-ISO9001-WORKSHOP', $rawEvidence);
        $this->assertTrue($isValid);

        $isTampered = $this->service->verifyClaimHash('CLAIM-ISO9001-WORKSHOP', 'TAMPERED-DATA');
        $this->assertFalse($isTampered);

        // 484.3 Calculate trust score
        $score = $this->service->calculateTrustScore('DOM-FLEET-QUALITY', 98.00, 100.00, true);
        $this->assertEquals(99.00, (float) $score->composite_trust_score);

        // 484.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unhashed_claim_and_unverified_trust_score_edge_cases(): void
    {
        // 484.5 Edge case: Claim without cryptographic evidence hash is rejected from publication
        try {
            $this->service->publishClaim('CLAIM-UNSUBSTANTIATED', 'sustainability', '100% Zero carbon fleet', '');
            $this->fail('Expected exception for unhashed claim');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('strictly require cryptographic evidence verification', $e->getMessage());
        }

        // 484.6 Risk: Unverified data input for trust score blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scores must be derived strictly from verified data');

        $this->service->calculateTrustScore('DOM-SHADY', 90.00, 90.00, false);
    }
}
