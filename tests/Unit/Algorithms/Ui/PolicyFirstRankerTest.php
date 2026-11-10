<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\PolicyFirstRanker;
use Modules\Shared\Domain\Algorithms\Ui\PolicyRankingCertificateVerifier;
use Tests\TestCase;

class PolicyFirstRankerTest extends TestCase
{
    private PolicyFirstRanker $ranker;

    private PolicyRankingCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranker = new PolicyFirstRanker;
        $this->verifier = new PolicyRankingCertificateVerifier;
    }

    public function test_rank_and_filter_zero_leak(): void
    {
        $candidates = [
            [
                'id' => 'doc-public',
                'title' => 'Public Notice',
                'score' => 2.5,
                'required_permission' => null,
            ],
            [
                'id' => 'doc-secret',
                'title' => 'Secret Financial Audit',
                'score' => 4.0,
                'required_permission' => 'finance.confidential.view',
            ],
        ];

        // Tamu / Tanpa login
        $guestResult = $this->ranker->rankAndFilter($candidates, null);
        $this->assertCount(1, $guestResult['results']);
        $this->assertEquals('doc-public', $guestResult['results'][0]['id']);
        $this->assertEquals(1, $guestResult['filtered_out_count']);
        $this->assertTrue($this->verifier->verify($guestResult['certificate']));

        // Bukti sabotase verifikasi
        $tamperedCert = $guestResult['certificate'];
        $tamperedCert['payload']['allowed_count'] = 99;
        $this->assertFalse($this->verifier->verify($tamperedCert));
    }
}
