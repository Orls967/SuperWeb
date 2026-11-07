<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Venue\Domain\Algorithms\ZoneCapacityCertificate;
use Modules\Venue\Domain\Algorithms\ZoneCapacityCertificateVerifier;
use Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor;
use PHPUnit\Framework\TestCase;

final class ZoneCapacityGovernorTest extends TestCase
{
    public function test_prompt_vectors(): void
    {
        $legal = 500;
        $marginBps = 500;
        $rate = 30;

        // Kapasitas efektif = 475
        $effective = ZoneCapacityGovernor::calculateEffectiveCapacity($legal, $marginBps);
        $this->assertSame(475, $effective);

        // Menit-1: okupansi 460, datang 20 -> masuk 15, tolak 5
        $m1 = ZoneCapacityGovernor::admit(
            legalCapacity: $legal,
            safetyMarginBps: $marginBps,
            ratePerMinute: $rate,
            currentOccupancy: 460,
            waitingPatrons: 20
        );
        $this->assertSame(15, $m1['admitted']);
        $this->assertSame(5, $m1['rejected']);
        $this->assertSame(475, $m1['occupancy_after']);

        // Menit-2: keluar 10, datang 40 -> masuk 10, tolak 30; okupansi 475
        $m2 = ZoneCapacityGovernor::admit(
            legalCapacity: $legal,
            safetyMarginBps: $marginBps,
            ratePerMinute: $rate,
            currentOccupancy: 475 - 10, // 465
            waitingPatrons: 40
        );
        $this->assertSame(10, $m2['admitted']);
        $this->assertSame(30, $m2['rejected']);
        $this->assertSame(475, $m2['occupancy_after']);
    }

    public function test_corrupted_certificate_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cert = new ZoneCapacityCertificate(
            legalCapacity: 500,
            safetyMarginBps: 500,
            effectiveCapacity: 475,
            ratePerMinute: 30,
            occupancyBefore: 460,
            waitingPatrons: 20,
            admittedPatrons: 15,
            rejectedPatrons: 5,
            occupancyAfter: 475,
            hmacSignature: 'invalid-signature'
        );

        ZoneCapacityCertificateVerifier::verify($cert);
    }
}
