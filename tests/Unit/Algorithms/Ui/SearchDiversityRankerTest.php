<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\DiversityCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Ui\SearchDiversityRanker;
use PHPUnit\Framework\TestCase;

class SearchDiversityRankerTest extends TestCase
{
    private SearchDiversityRanker $ranker;

    private DiversityCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranker = new SearchDiversityRanker(0.70);
        $this->verifier = new DiversityCertificateVerifier;
    }

    public function test_rank_diverse_penalizes_redundancy_and_verifies_certificate(): void
    {
        $candidates = [
            ['id' => 'doc-1', 'module' => 'Contract', 'entity_type' => 'Agreement', 'score' => 5.0],
            ['id' => 'doc-2', 'module' => 'Contract', 'entity_type' => 'Agreement', 'score' => 4.9],
            ['id' => 'doc-3', 'module' => 'Asset', 'entity_type' => 'Machinery', 'score' => 4.8],
            ['id' => 'doc-4', 'module' => 'Logistics', 'entity_type' => 'Shipment', 'score' => 4.7],
        ];

        $res = $this->ranker->rankDiverse($candidates, 4);

        $this->assertCount(4, $res['ranked']);
        $this->assertEquals('doc-1', $res['ranked'][0]['id']);
        $this->assertTrue($this->verifier->verify($res['certificate']));

        // Bukti sabotase verifikasi
        $tampered = $res['certificate'];
        $tampered['payload']['lambda'] = 0.99;
        $this->assertFalse($this->verifier->verify($tampered));
    }
}
