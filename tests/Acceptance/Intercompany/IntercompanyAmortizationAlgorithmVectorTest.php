<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Algorithms\Certificates\IntercompanyAmortizationCertificateVerifier;
use Modules\Intercompany\Domain\Algorithms\IntercompanyAmortizationScheduler;
use Modules\Intercompany\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class IntercompanyAmortizationAlgorithmVectorTest extends TestCase
{
    public function test_intercompany_amortization_scheduler_matches_all_prompt_vectors(): void
    {
        $scheduler = new IntercompanyAmortizationScheduler;

        // Vektor 1: Pinjaman Rp 120.000.000, 12 Bulan, Bunga 600 bps + 50 bps spread = 650 bps (6,50%), PPh 23 1500 bps (15,00%)
        // Pokok per bulan: 10.000.000 IDR
        // Bulan 1: Saldo Awal 120M, Bunga Kotor = floor(120M * 650 * 30 / 3.6M) = 650.000 IDR
        // Pajak PPh 23 = floor(650.000 * 1500 / 10000) = 97.500 IDR
        // Bunga Bersih = 650.000 - 97.500 = 552.500 IDR
        // Total Kas Keluar = 10.000.000 + 552.500 = 10.552.500 IDR
        $res1 = $scheduler->generateSchedule(
            principalMinor: 120_000_000,
            baseRateBps: 600,
            spreadBps: 50,
            withholdingTaxBps: 1500,
            periods: 12,
            startDate: '2026-01-01'
        );

        $this->assertCount(12, $res1->schedules);
        $this->assertSame(120_000_000, $res1->totalPrincipalMinor);
        $this->assertSame(10_000_000, $res1->schedules[0]->principalDueMinor);
        $this->assertSame(650_000, $res1->schedules[0]->interestGrossMinor);
        $this->assertSame(97_500, $res1->schedules[0]->taxWithheldMinor);
        $this->assertSame(552_500, $res1->schedules[0]->interestNetMinor);
        $this->assertSame(10_552_500, $res1->schedules[0]->cashDisbursedMinor);
        $this->assertSame(110_000_000, $res1->schedules[0]->endingBalanceMinor);

        // Invarian: Total Pokok di skedul tepat sama dengan pokok awal (tanpa penny drift)
        $sumPrincipal = array_sum(array_map(fn ($s) => $s->principalDueMinor, $res1->schedules));
        $this->assertSame(120_000_000, $sumPrincipal);
        $this->assertTrue(IntercompanyAmortizationCertificateVerifier::verify($res1->certificate));

        // Vektor 2: Penyerapan Sisa Remainder Pokok Ganjil (Rp 100.000.000 / 3 bulan)
        // Periode 1: 33.333.333
        // Periode 2: 33.333.333
        // Periode 3: 33.333.334 (penyerapan sisa)
        $res2 = $scheduler->generateSchedule(
            principalMinor: 100_000_000,
            baseRateBps: 500,
            spreadBps: 100,
            withholdingTaxBps: 1500,
            periods: 3,
            startDate: '2026-01-01'
        );

        $this->assertSame(33_333_333, $res2->schedules[0]->principalDueMinor);
        $this->assertSame(33_333_333, $res2->schedules[1]->principalDueMinor);
        $this->assertSame(33_333_334, $res2->schedules[2]->principalDueMinor);
        $sumPrincipal2 = array_sum(array_map(fn ($s) => $s->principalDueMinor, $res2->schedules));
        $this->assertSame(100_000_000, $sumPrincipal2);

        // Vektor 3: Pinjaman WPLN Luar Negeri dengan PPh 26 (2000 bps / 20%)
        // Pokok 50.000.000, Bunga 800 bps, 30 hari -> Gross = 333.333, WHT 20% = 66.666, Net = 266.667
        $res3 = $scheduler->generateSchedule(
            principalMinor: 50_000_000,
            baseRateBps: 800,
            spreadBps: 0,
            withholdingTaxBps: 2000,
            periods: 1,
            startDate: '2026-01-01'
        );

        $this->assertSame(333_333, $res3->schedules[0]->interestGrossMinor);
        $this->assertSame(66_666, $res3->schedules[0]->taxWithheldMinor);
        $this->assertSame(266_667, $res3->schedules[0]->interestNetMinor);

        // Vektor 4: Validasi periode <= 0 melempar exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('INVALID_PERIODS_COUNT');
        $scheduler->generateSchedule(100_000, 500, 0, 1500, 0, '2026-01-01');
    }

    public function test_corrupted_intercompany_amortization_certificate_throws_exception(): void
    {
        $scheduler = new IntercompanyAmortizationScheduler;
        $res = $scheduler->generateSchedule(120_000_000, 600, 50, 1500, 12, '2026-01-01');

        $corrupt = $res->certificate;
        $corrupt->sha256 = 'corrupted_amortization_hash';

        $this->expectException(InvalidCertificateException::class);
        IntercompanyAmortizationCertificateVerifier::assertValid($corrupt);
    }
}
