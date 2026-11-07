<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitCalculator;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitCertificate;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitInput;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitVerifier;
use Tests\TestCase;

final class ArtistDoorSplitCalculatorTest extends TestCase
{
    private ArtistDoorSplitCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ArtistDoorSplitCalculator;
    }

    public function test_versus_deal_guarantee_higher(): void
    {
        // Guarantee Rp 100jt vs 20% dari Net Pintu (Gross Rp 300jt - Exp Rp 100jt = Rp 200jt -> 20% = Rp 40jt)
        // Gross Pay = max(100jt, 40jt) = 100jt
        // WHT 20% = 20jt, Advance = 30jt -> Net = 50jt
        $input = new ArtistDoorSplitInput(
            dealType: 'versus',
            guaranteeIdr: 100_000_000,
            doorGrossIdr: 300_000_000,
            approvedExpensesIdr: 100_000_000,
            shareBps: 2_000,
            whtBps: 2_000,
            advancePaidIdr: 30_000_000,
            currency: 'IDR',
            exchangeRateIdr: 1
        );

        $result = $this->calculator->calculate($input);

        $this->assertSame(100_000_000, $result->grossPayIdr);
        $this->assertSame(20_000_000, $result->whtIdr);
        $this->assertSame(50_000_000, $result->netPayableIdr);
        $this->assertTrue($result->certificate->isValid());
    }

    public function test_versus_deal_door_share_higher_with_usd_conversion(): void
    {
        // Guarantee Rp 100jt vs 30% dari Net Pintu (Gross Rp 600jt - Exp Rp 150jt = Rp 450jt -> 30% = Rp 135jt)
        // Gross Pay = Rp 135jt
        // WHT 20% = Rp 27jt, Advance = Rp 50jt -> Net IDR = Rp 58jt
        // Kurs Rp 16.250 -> Valas = floor(58.000.000 * 100 / 16.250) = 356.923 cents ($3.569,23)
        $input = new ArtistDoorSplitInput(
            dealType: 'versus',
            guaranteeIdr: 100_000_000,
            doorGrossIdr: 600_000_000,
            approvedExpensesIdr: 150_000_000,
            shareBps: 3_000,
            whtBps: 2_000,
            advancePaidIdr: 50_000_000,
            currency: 'USD',
            exchangeRateIdr: 16_250
        );

        $result = $this->calculator->calculate($input);

        $this->assertSame(135_000_000, $result->grossPayIdr);
        $this->assertSame(27_000_000, $result->whtIdr);
        $this->assertSame(58_000_000, $result->netPayableIdr);
        $this->assertSame(356_923, $result->valasMinorCents);
        $this->assertTrue($result->certificate->isValid());
    }

    public function test_tampered_certificate_is_rejected(): void
    {
        $input = new ArtistDoorSplitInput(
            dealType: 'guarantee',
            guaranteeIdr: 50_000_000,
            doorGrossIdr: 100_000_000,
            approvedExpensesIdr: 20_000_000,
            shareBps: 1_000,
            whtBps: 1_000,
            advancePaidIdr: 0
        );

        $result = $this->calculator->calculate($input);

        // Ubah nilai sertifikat secara ilegal (tampering)
        $tamperedCert = new ArtistDoorSplitCertificate(
            dealType: $result->certificate->dealType,
            guaranteeIdr: $result->certificate->guaranteeIdr,
            doorGrossIdr: $result->certificate->doorGrossIdr,
            approvedExpensesIdr: $result->certificate->approvedExpensesIdr,
            shareBps: $result->certificate->shareBps,
            whtBps: $result->certificate->whtBps,
            advancePaidIdr: $result->certificate->advancePaidIdr,
            grossPayIdr: 999_999_999, // Nilai dimanipulasi
            whtIdr: $result->certificate->whtIdr,
            netPayableIdr: $result->certificate->netPayableIdr,
            valasMinorCents: $result->certificate->valasMinorCents,
            hmacSignature: $result->certificate->hmacSignature
        );

        $this->expectException(InvalidArgumentException::class);
        ArtistDoorSplitVerifier::verify($tamperedCert);
    }
}
