<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Market\CallAuctionClearing;
use Modules\Shared\Domain\Algorithms\Market\CallAuctionClearingCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Market\CallAuctionClearingResult;
use RuntimeException;
use Tests\TestCase;

class CallAuctionClearingTest extends TestCase
{
    private CallAuctionClearing $solver;

    private CallAuctionClearingCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->solver = new CallAuctionClearing;
        $this->verifier = new CallAuctionClearingCertificateVerifier;
    }

    public function test_exact_prompt_vector_d24_3_clears_at_1350_with_22t_volume(): void
    {
        // Vektor prompt D24-3:
        // Bid: B1 10 t @1.500, B2 15 t @1.400, B3 10 t @1.200
        // Ask: S1 12 t @1.100, S2 10 t @1.300, S3 10 t @1.450
        $bids = [
            ['id' => 'B1', 'quantity' => 10, 'price' => 1500, 'created_at' => 1],
            ['id' => 'B2', 'quantity' => 15, 'price' => 1400, 'created_at' => 2],
            ['id' => 'B3', 'quantity' => 10, 'price' => 1200, 'created_at' => 3],
        ];

        $asks = [
            ['id' => 'S1', 'quantity' => 12, 'price' => 1100, 'created_at' => 1],
            ['id' => 'S2', 'quantity' => 10, 'price' => 1300, 'created_at' => 2],
            ['id' => 'S3', 'quantity' => 10, 'price' => 1450, 'created_at' => 3],
        ];

        $result = $this->solver->solve($bids, $asks);

        $this->assertSame(22, $result->clearingVolume);
        $this->assertSame(1350, $result->clearingPrice);

        // Tereksekusi: S1 12, S2 10, B1 10, B2 12 (sisa 3); B3 dan S3 tidak tereksekusi
        $this->assertSame(12, $result->askAllocations['S1']);
        $this->assertSame(10, $result->askAllocations['S2']);
        $this->assertSame(0, $result->askAllocations['S3']);

        $this->assertSame(10, $result->bidAllocations['B1']);
        $this->assertSame(12, $result->bidAllocations['B2']);
        $this->assertSame(0, $result->bidAllocations['B3']);

        // Verifikasi sertifikat
        $this->assertTrue($this->verifier->verify($bids, $asks, $result));
        $this->assertSame(1400, $result->certificate['highest_unexecuted_bid_price']);
        $this->assertSame(1450, $result->certificate['lowest_unexecuted_ask_price']);
        $this->assertTrue($result->certificate['highest_unexecuted_bid_price'] < $result->certificate['lowest_unexecuted_ask_price']);
    }

    public function test_verifier_throws_exception_when_certificate_is_corrupted(): void
    {
        $bids = [['id' => 'B1', 'quantity' => 10, 'price' => 1500, 'created_at' => 1]];
        $asks = [['id' => 'S1', 'quantity' => 10, 'price' => 1100, 'created_at' => 1]];

        $validResult = $this->solver->solve($bids, $asks);
        $this->assertTrue($this->verifier->verify($bids, $asks, $validResult));

        $tampered = new CallAuctionClearingResult(
            clearingPrice: 1600,
            clearingVolume: 10,
            bidAllocations: ['B1' => 10],
            askAllocations: ['S1' => 10],
            trades: [['bid_id' => 'B1', 'ask_id' => 'S1', 'quantity' => 10, 'price' => 1600]],
            certificate: $validResult->certificate,
        );

        $this->expectException(RuntimeException::class);
        $this->verifier->verify($bids, $asks, $tampered);
    }
}
