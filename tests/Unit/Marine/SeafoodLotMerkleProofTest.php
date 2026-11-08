<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Modules\Marine\Domain\Algorithms\SeafoodLotMerkleProofGenerator;
use Tests\TestCase;

class SeafoodLotMerkleProofTest extends TestCase
{
    protected SeafoodLotMerkleProofGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SeafoodLotMerkleProofGenerator;
    }

    public function test_merkle_tree_construction_and_cryptographic_proof_verification(): void
    {
        $events = [
            ['stage' => 'HATCHERY', 'cert' => 'SPF-001'],
            ['stage' => 'POND', 'code' => 'TAMBAK-A1'],
            ['stage' => 'FEED', 'type' => 'Japfa-Pellet'],
            ['stage' => 'WATER', 'do' => 5.5],
            ['stage' => 'HARVEST', 'kg' => 3300],
            ['stage' => 'COLD_CHAIN', 'temp' => -20.0],
        ];

        $tree = $this->generator->buildMerkleTree($events);

        $this->assertNotEmpty($tree['root_hash']);
        $this->assertEquals(64, strlen($tree['root_hash']));
        $this->assertEquals(6, $tree['leaf_count']);

        // Verifikasi proof untuk setiap leaf
        for ($i = 0; $i < 6; $i++) {
            $proof = $this->generator->generateProof($tree, $i);
            $leafHash = $tree['leaves'][$i];
            $isValid = $this->generator->verifyProof($leafHash, $proof, $tree['root_hash']);
            $this->assertTrue($isValid, "Merkle proof verification failed for leaf index {$i}");
        }
    }

    public function test_tampered_leaf_hash_fails_proof_verification(): void
    {
        $events = [
            'event_1_valid',
            'event_2_valid',
        ];

        $tree = $this->generator->buildMerkleTree($events);
        $proof = $this->generator->generateProof($tree, 0);

        $tamperedLeafHash = hash('sha256', 'event_1_fake_tampered');
        $isValid = $this->generator->verifyProof($tamperedLeafHash, $proof, $tree['root_hash']);

        $this->assertFalse($isValid);
    }
}
