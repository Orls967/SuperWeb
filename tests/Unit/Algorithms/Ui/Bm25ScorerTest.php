<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\Bm25CertificateVerifier;
use Modules\Shared\Domain\Algorithms\Ui\Bm25Scorer;
use PHPUnit\Framework\TestCase;

class Bm25ScorerTest extends TestCase
{
    private Bm25Scorer $scorer;

    private Bm25CertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new Bm25Scorer;
        $this->verifier = new Bm25CertificateVerifier;
    }

    public function test_idf_calculation(): void
    {
        $idf = $this->scorer->calculateIdf(100, 10);
        $this->assertGreaterThan(0.0, $idf);

        // Edge case document frequency 0
        $zeroIdf = $this->scorer->calculateIdf(100, 0);
        $this->assertEquals(0.0, $zeroIdf);
    }

    public function test_score_term_proportionality(): void
    {
        $idf = 2.5;
        $scoreLow = $this->scorer->scoreTerm(1, 50, 50.0, $idf);
        $scoreHigh = $this->scorer->scoreTerm(5, 50, 50.0, $idf);

        $this->assertGreaterThan(0.0, $scoreLow);
        $this->assertGreaterThan($scoreLow, $scoreHigh);
    }

    public function test_compute_and_certificate_verification(): void
    {
        $queryTerms = ['engine' => 1, 'repair' => 1];
        $docTerms = ['engine' => 3, 'repair' => 2];
        $idfs = ['engine' => 1.8, 'repair' => 2.1];

        $result = $this->scorer->compute(
            documentId: 'doc-101',
            queryTerms: $queryTerms,
            docTerms: $docTerms,
            idfs: $idfs,
            docLength: 40,
            avgDocLength: 45.0,
            fieldWeights: ['engine' => 2.0, 'repair' => 1.0]
        );

        $this->assertGreaterThan(0.0, $result['score']);
        $this->assertArrayHasKey('certificate', $result);
        $this->assertTrue($this->verifier->verify($result['certificate']));

        // Bukti sabotase: manipulasi payload menggagalkan verifikasi sertifikat
        $corruptCert = $result['certificate'];
        $corruptCert['payload']['total_score'] = 999.99;
        $this->assertFalse($this->verifier->verify($corruptCert));
    }
}
