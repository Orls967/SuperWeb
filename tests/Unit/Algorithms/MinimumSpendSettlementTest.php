<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Venue\Domain\Algorithms\MinimumSpendCertificate;
use Modules\Venue\Domain\Algorithms\MinimumSpendCertificateVerifier;
use Modules\Venue\Domain\Algorithms\MinimumSpendSettlement;
use PHPUnit\Framework\TestCase;

final class MinimumSpendSettlementTest extends TestCase
{
    public function test_prompt_vectors(): void
    {
        $min = 5_000_000;
        $dep = 2_000_000;

        // Vektor 1: Belanja 6.300.000 -> capture 2.000.000 + tagih 4.300.000
        $c1 = MinimumSpendSettlement::calculate(
            minimumSpendIdr: $min,
            depositHeldIdr: $dep,
            actualSpendIdr: 6_300_000
        );
        $this->assertSame(2_000_000, $c1->capturedDepositIdr);
        $this->assertSame(4_300_000, $c1->additionalChargeIdr);
        $this->assertSame(6_300_000, $c1->totalBillIdr);
        $this->assertSame(0, $c1->shortfallIdr);

        // Vektor 2: Belanja 3.200.000 -> capture 2.000.000 + tagih 3.000.000; F&B 3.200.000 + shortfall 1.800.000
        $c2 = MinimumSpendSettlement::calculate(
            minimumSpendIdr: $min,
            depositHeldIdr: $dep,
            actualSpendIdr: 3_200_000
        );
        $this->assertSame(2_000_000, $c2->capturedDepositIdr);
        $this->assertSame(3_000_000, $c2->additionalChargeIdr);
        $this->assertSame(5_000_000, $c2->totalBillIdr);
        $this->assertSame(1_800_000, $c2->shortfallIdr);

        // Vektor 3: No-show -> hangus 1.000.000, refund 1.000.000
        $c3 = MinimumSpendSettlement::calculate(
            minimumSpendIdr: $min,
            depositHeldIdr: $dep,
            actualSpendIdr: 0,
            isNoShow: true,
            noShowForfeitureBps: 5000
        );
        $this->assertSame(1_000_000, $c3->forfeitedDepositIdr);
        $this->assertSame(1_000_000, $c3->refundedDepositIdr);
    }

    public function test_corrupted_certificate_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cert = new MinimumSpendCertificate(
            minimumSpendIdr: 5_000_000,
            depositHeldIdr: 2_000_000,
            actualSpendIdr: 6_300_000,
            isNoShow: false,
            totalBillIdr: 6_300_000,
            capturedDepositIdr: 2_000_000,
            additionalChargeIdr: 4_300_000,
            shortfallIdr: 0,
            forfeitedDepositIdr: 0,
            refundedDepositIdr: 0,
            hmacSignature: 'invalid-signature'
        );

        MinimumSpendCertificateVerifier::verify($cert);
    }
}
