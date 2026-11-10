<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\JaccardCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Ui\TrigramJaccardMatcher;
use PHPUnit\Framework\TestCase;

class TrigramJaccardMatcherTest extends TestCase
{
    private TrigramJaccardMatcher $matcher;

    private JaccardCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new TrigramJaccardMatcher;
        $this->verifier = new JaccardCertificateVerifier;
    }

    public function test_extract_trigrams(): void
    {
        $trigrams = $this->matcher->extractTrigrams('fleet');
        $this->assertArrayHasKey('fle', $trigrams);
        $this->assertArrayHasKey('lee', $trigrams);
        $this->assertArrayHasKey('eet', $trigrams);
    }

    public function test_jaccard_similarity_exact_and_different(): void
    {
        $setA = $this->matcher->extractTrigrams('workshop');
        $setB = $this->matcher->extractTrigrams('workshop');
        $exactSim = $this->matcher->calculateJaccardSimilarity($setA, $setB);
        $this->assertEquals(1.0, $exactSim);

        $setC = $this->matcher->extractTrigrams('airplane');
        $diffSim = $this->matcher->calculateJaccardSimilarity($setA, $setC);
        $this->assertEquals(0.0, $diffSim);
    }

    public function test_find_best_match_with_certificate(): void
    {
        $corpus = ['invoice', 'inventory', 'investment', 'investor'];
        $result = $this->matcher->findBestMatch('invoce', $corpus, 0.40);

        $this->assertNotNull($result);
        $this->assertEquals('invoice', $result['match']);
        $this->assertGreaterThan(0.5, $result['similarity']);

        $this->assertTrue($this->verifier->verify($result['certificate']));

        // Bukti sabotase sertifikat
        $corrupt = $result['certificate'];
        $corrupt['payload']['similarity'] = 1.0;
        $this->assertFalse($this->verifier->verify($corrupt));
    }
}
