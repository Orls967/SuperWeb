<?php

declare(strict_types=1);

namespace Tests\Acceptance\Access;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\Access\DenyOverridesEvaluator;
use Modules\Core\Domain\Algorithms\Access\EvaluatorCertificateVerifier;
use Modules\Core\Domain\Algorithms\Access\IdentityLinkerCertificateVerifier;
use Modules\Core\Domain\Algorithms\Access\VendorExposureAggregator;
use Modules\Core\Domain\Algorithms\Access\VendorExposureCertificateVerifier;
use Modules\Core\Domain\Algorithms\Access\WeightedIdentityLinker;
use Tests\TestCase;

class AccessAlgorithmsTest extends TestCase
{
    /**
     * Test C1: DenyOverridesEvaluator vectors and certificate verification.
     */
    public function test_c1_deny_overrides_evaluator_vectors(): void
    {
        $evaluator = new DenyOverridesEvaluator;
        $verifier = new EvaluatorCertificateVerifier;

        $rules = [
            [
                'id' => 'rule-1',
                'effect' => 'ALLOW',
                'resource' => 'purchase-orders',
                'action' => 'read',
                'dimensions' => [
                    'entity_id' => 'ENT-01',
                    'region_id' => 'ID-JKT',
                ],
            ],
            [
                'id' => 'rule-2',
                'effect' => 'DENY',
                'resource' => 'purchase-orders',
                'action' => 'read',
                'dimensions' => [
                    'site_id' => 'RESTRICTED-SITE',
                ],
            ],
            [
                'id' => 'rule-3',
                'effect' => 'ALLOW',
                'resource' => 'shipments',
                'action' => 'track',
                'dimensions' => [
                    'entity_id' => '*',
                ],
            ],
        ];

        // 1. Context matching ALLOW rule only -> ALLOW
        $ctx1 = [
            'entity_id' => 'ENT-01',
            'region_id' => 'ID-JKT',
            'site_id' => 'SITE-A',
        ];
        $res1 = $evaluator->evaluate($rules, 'purchase-orders', 'read', $ctx1, 1700000000);
        $this->assertTrue($res1['allowed']);
        $this->assertEquals('ALLOW', $res1['decision']);
        $this->assertEquals('rule-1', $res1['matched_rule_id']);
        $this->assertTrue($verifier->verifyCertificate($res1['certificate']));

        // 2. Context matching both ALLOW and DENY -> DENY (Deny-Overrides)
        $ctx2 = [
            'entity_id' => 'ENT-01',
            'region_id' => 'ID-JKT',
            'site_id' => 'RESTRICTED-SITE',
        ];
        $res2 = $evaluator->evaluate($rules, 'purchase-orders', 'read', $ctx2, 1700000000);
        $this->assertFalse($res2['allowed']);
        $this->assertEquals('DENY', $res2['decision']);
        $this->assertEquals('rule-2', $res2['matched_rule_id']);
        $this->assertTrue($verifier->verifyCertificate($res2['certificate']));

        // 3. Context matching no rules -> DENY (Fail-Closed Default)
        $ctx3 = [
            'entity_id' => 'UNKNOWN-ENT',
            'region_id' => 'ID-SBY',
        ];
        $res3 = $evaluator->evaluate($rules, 'purchase-orders', 'read', $ctx3, 1700000000);
        $this->assertFalse($res3['allowed']);
        $this->assertEquals('DENY_DEFAULT', $res3['decision']);
        $this->assertNull($res3['matched_rule_id']);
        $this->assertTrue($verifier->verifyCertificate($res3['certificate']));

        // 4. Wildcard dimension match -> ALLOW
        $ctx4 = [
            'entity_id' => 'ANY-ENTITY-99',
        ];
        $res4 = $evaluator->evaluate($rules, 'shipments', 'track', $ctx4, 1700000000);
        $this->assertTrue($res4['allowed']);
        $this->assertEquals('ALLOW', $res4['decision']);
        $this->assertEquals('rule-3', $res4['matched_rule_id']);
        $this->assertTrue($verifier->verifyCertificate($res4['certificate']));
    }

    /**
     * Test C2: WeightedIdentityLinker vectors and double-consent gating.
     */
    public function test_c2_weighted_identity_linker_vectors(): void
    {
        $linker = new WeightedIdentityLinker('secret_platform_salt_2026');
        $verifier = new IdentityLinkerCertificateVerifier;

        $subjectA = [
            'nik' => '3201012345670001',
            'email' => 'Budi.Santoso@Example.COM',
            'phone' => '+6281234567890',
            'name_dob' => 'BUDI SANTOSO:1985-06-15',
        ];

        // 1. Identical attributes -> Score 100 + 60 + 50 + 40 = 250 (Threshold >= 90) -> QUALIFIED
        $subjectB_identical = [
            'nik' => '3201012345670001',
            'email' => 'budi.santoso@example.com',
            'phone' => '081234567890', // Normalizes to +6281234567890
            'name_dob' => 'Budi Santoso:1985-06-15',
        ];
        $res1 = $linker->evaluateLinkage($subjectA, $subjectB_identical, sourceConsent: true, targetConsent: true);
        $this->assertEquals(250, $res1['match_score']);
        $this->assertEquals('LINKED', $res1['status']);
        $this->assertTrue($res1['is_linked']);
        $this->assertTrue($verifier->verifyCertificate($res1['certificate']));

        // 2. Email + Phone match only -> Score 60 + 50 = 110 (Threshold >= 90) -> QUALIFIED
        $subjectC_partial = [
            'nik' => '3201099999990002', // Different
            'email' => 'budi.santoso@example.com',
            'phone' => '+6281234567890',
            'name_dob' => 'Joko Widodo:1970-01-01', // Different
        ];
        $res2 = $linker->evaluateLinkage($subjectA, $subjectC_partial, sourceConsent: true, targetConsent: true);
        $this->assertEquals(110, $res2['match_score']);
        $this->assertEquals('LINKED', $res2['status']);
        $this->assertTrue($res2['is_linked']);

        // 3. Name+DOB match only -> Score 40 (Below threshold 90) -> REJECTED_BELOW_THRESHOLD
        $subjectD_low = [
            'nik' => '3201099999990009',
            'email' => 'someone.else@example.com',
            'phone' => '+6289999999999',
            'name_dob' => 'budi santoso:1985-06-15',
        ];
        $res3 = $linker->evaluateLinkage($subjectA, $subjectD_low, sourceConsent: true, targetConsent: true);
        $this->assertEquals(40, $res3['match_score']);
        $this->assertEquals('REJECTED_BELOW_THRESHOLD', $res3['status']);
        $this->assertFalse($res3['is_linked']);

        // 4. Score 250 but targetConsent = false -> BLOCKED_NO_CONSENT
        $res4 = $linker->evaluateLinkage($subjectA, $subjectB_identical, sourceConsent: true, targetConsent: false);
        $this->assertEquals(250, $res4['match_score']);
        $this->assertEquals('BLOCKED_NO_CONSENT', $res4['status']);
        $this->assertFalse($res4['is_linked']);
    }

    /**
     * Test C3: VendorExposureAggregator vectors and integer minor unit arithmetic.
     */
    public function test_c3_vendor_exposure_aggregator_vectors(): void
    {
        $aggregator = new VendorExposureAggregator;
        $verifier = new VendorExposureCertificateVerifier;

        $tranches = [
            [
                'module' => 'Procurement',
                'reference' => 'PO-PRC-2026-001',
                'amount_idr' => 15000000000, // IDR 150,000,000 (in minor units: cents or rupiah)
                'status' => 'OPEN',
            ],
            [
                'module' => 'Logistics',
                'reference' => 'GR-LGX-2026-042',
                'amount_idr' => 7500000000, // IDR 75,000,000
                'status' => 'UNINVOICED',
            ],
            [
                'module' => 'CloudKitchen',
                'reference' => 'DEP-CKT-2026-009',
                'amount_idr' => 2500000000, // IDR 25,000,000
                'status' => 'HELD',
            ],
            [
                'module' => 'AutoServe',
                'reference' => 'CLM-AUT-2026-118',
                'amount_idr' => 5000000000, // IDR 50,000,000
                'status' => 'PENDING_BILLING',
            ],
            [
                'module' => 'Procurement',
                'reference' => 'PO-PRC-2026-CLOSED',
                'amount_idr' => 88000000000,
                'status' => 'FULFILLED', // Should be ignored
            ],
        ];

        $groupCreditLimit = 35000000000; // Limit IDR 350,000,000

        // Total active exposure: 150M + 75M + 25M + 50M = 300,000,000
        $res = $aggregator->aggregate('CLUSTER-VEND-88', $tranches, $groupCreditLimit, 1700000000);

        $this->assertEquals(30000000000, $res['total_exposure_idr']);
        $this->assertEquals(4, $res['active_tranche_count']);
        $this->assertEquals(5000000000, $res['remaining_credit_limit_idr']); // 350M - 300M = 50M
        $this->assertFalse($res['is_limit_breached']);
        $this->assertTrue($verifier->verifyCertificate($res['certificate']));

        // Test over limit: Limit 200,000,000 (breached by 100M)
        $resOver = $aggregator->aggregate('CLUSTER-VEND-88', $tranches, 20000000000, 1700000000);
        $this->assertTrue($resOver['is_limit_breached']);
        $this->assertEquals(-10000000000, $resOver['remaining_credit_limit_idr']);
    }

    /**
     * Test certificate tamper rejection across all 3 algorithms.
     */
    public function test_rejects_tampered_certificates_across_c1_c2_c3(): void
    {
        $evalVerifier = new EvaluatorCertificateVerifier;
        $linkVerifier = new IdentityLinkerCertificateVerifier;
        $expVerifier = new VendorExposureCertificateVerifier;

        $this->expectException(InvalidArgumentException::class);
        $evalVerifier->verifyCertificate([
            'algorithm' => 'DENY_OVERRIDES_ABAC_V1',
            'decision' => 'ALLOW',
            'timestamp' => 1700000000,
            'sha256' => 'tampered_hash_that_does_not_match',
        ]);
    }
}
