<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\Biometric\BiometricLivenessMatcherEngine;
use RuntimeException;
use Tests\TestCase;

class BiometricLivenessMatcherEngineTest extends TestCase
{
    private BiometricLivenessMatcherEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new BiometricLivenessMatcherEngine;
    }

    public function test_matches_valid_face_template_with_liveness_proof(): void
    {
        $cert = $this->engine->match(
            userId: 101,
            featureVector: 'VECTOR-FACE-VALID-TEST-1234',
            challengeProof: 'CHALLENGE-BLINK-SUCCESS',
            saltSecret: 'UNIT_TEST_SALT_SECRET'
        );

        $this->assertTrue($cert->isMatched);
        $this->assertGreaterThanOrEqual(8500, $cert->livenessScoreBps);
        $this->assertEquals(64, strlen($cert->templateHash));
        $this->assertTrue($cert->verifySignature('UNIT_TEST_SALT_SECRET'));
    }

    public function test_rejects_spoofed_photo_liveness(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Liveness verification failed');

        $this->engine->match(
            userId: 101,
            featureVector: 'VECTOR-FACE-VALID-TEST-1234',
            challengeProof: 'STATIC-PHOTO-REPLAY-ATTACK',
            saltSecret: 'UNIT_TEST_SALT_SECRET'
        );
    }

    public function test_flags_mismatched_probe_feature_vector(): void
    {
        $cert = $this->engine->match(
            userId: 101,
            featureVector: 'VECTOR-FACE-WRONG-PERSON',
            challengeProof: 'CHALLENGE-BLINK-SUCCESS',
            saltSecret: 'UNIT_TEST_SALT_SECRET'
        );

        $this->assertFalse($cert->isMatched);
        $this->assertTrue($cert->verifySignature('UNIT_TEST_SALT_SECRET'));
    }
}
