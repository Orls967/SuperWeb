<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Algorithms\Certificates\TransferPricingMarkupCertificateVerifier;
use Modules\Intercompany\Domain\Algorithms\TransferPricingMarkupCalculator;
use Modules\Intercompany\Domain\Exceptions\InvalidCertificateException;
use Tests\TestCase;

class TransferPricingMarkupAlgorithmVectorTest extends TestCase
{
    public function test_transfer_pricing_markup_calculator_matches_all_prompt_vectors(): void
    {
        $calculator = new TransferPricingMarkupCalculator;

        // Vektor 1: n = 7 pembanding [400, 600, 800, 1000, 1200, 1400, 1600]
        // Q1 = 600 bps, Median = 1000 bps, Q3 = 1400 bps
        // Biaya Rp 10.000.000, Harga Rp 11.200.000 -> Margin 1200 bps -> COMPLIANT
        $benchmark1 = [400, 600, 800, 1000, 1200, 1400, 1600];
        $res1 = $calculator->evaluateMargin(
            method: 'CPM',
            costMinor: 10_000_000,
            actualPriceMinor: 11_200_000,
            benchmarkMarginsBps: $benchmark1
        );

        $this->assertSame(1200, $res1->actualMarginBps);
        $this->assertSame(600, $res1->q1Bps);
        $this->assertSame(1000, $res1->medianBps);
        $this->assertSame(1400, $res1->q3Bps);
        $this->assertSame('COMPLIANT', $res1->status);
        $this->assertSame(0, $res1->adjustmentMinor);
        $this->assertTrue($res1->isCompliant());
        $this->assertTrue(TransferPricingMarkupCertificateVerifier::verify($res1->certificate));

        // Vektor 2: Margin di bawah rentang (Cost 10.000.000, Price 10.400.000 -> 400 bps < 600 bps)
        // Penyesuaian ke median (1000 bps = 10%): Target 11.000.000 -> Adjustment +600.000
        $res2 = $calculator->evaluateMargin(
            method: 'CPM',
            costMinor: 10_000_000,
            actualPriceMinor: 10_400_000,
            benchmarkMarginsBps: $benchmark1
        );

        $this->assertSame(400, $res2->actualMarginBps);
        $this->assertSame('BELOW_ARM_LENGTH', $res2->status);
        $this->assertFalse($res2->isCompliant());
        $this->assertSame(600_000, $res2->adjustmentMinor);
        $this->assertSame(11_000_000, $res2->targetPriceMinor);
        $this->assertTrue(TransferPricingMarkupCertificateVerifier::verify($res2->certificate));

        // Vektor 3: Margin di atas rentang (Cost 10.000.000, Price 11.800.000 -> 1800 bps > 1400 bps)
        // Penyesuaian ke median: Target 11.000.000 -> Adjustment -800.000
        $res3 = $calculator->evaluateMargin(
            method: 'CPM',
            costMinor: 10_000_000,
            actualPriceMinor: 11_800_000,
            benchmarkMarginsBps: $benchmark1
        );

        $this->assertSame(1800, $res3->actualMarginBps);
        $this->assertSame('ABOVE_ARM_LENGTH', $res3->status);
        $this->assertSame(-800_000, $res3->adjustmentMinor);
        $this->assertTrue(TransferPricingMarkupCertificateVerifier::verify($res3->certificate));

        // Vektor 4: Interpolasi Kuartil Data Genap n = 8:
        // [500, 700, 900, 1100, 1300, 1500, 1700, 1900]
        // Q1 pos = 2.25 -> 700 + 0.25*(200) = 750 bps
        // Median pos = 4.5 -> 1100 + 0.5*(200) = 1200 bps
        // Q3 pos = 6.75 -> 1500 + 0.75*(200) = 1650 bps
        $benchmark2 = [500, 700, 900, 1100, 1300, 1500, 1700, 1900];
        $res4 = $calculator->evaluateMargin(
            method: 'TNMM',
            costMinor: 50_000_000,
            actualPriceMinor: 56_000_000, // Margin = (56M - 50M)/50M = 1200 bps
            benchmarkMarginsBps: $benchmark2
        );

        $this->assertSame(750, $res4->q1Bps);
        $this->assertSame(1200, $res4->medianBps);
        $this->assertSame(1650, $res4->q3Bps);
        $this->assertSame(1200, $res4->actualMarginBps);
        $this->assertSame('COMPLIANT', $res4->status);

        // Vektor 5: Validasi pengecualian n < 4 benchmark data
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('INSUFFICIENT_BENCHMARK_DATA');
        $calculator->evaluateMargin('CPM', 1000, 1100, [500, 600, 700]);
    }

    public function test_corrupted_transfer_pricing_certificate_throws_exception(): void
    {
        $calculator = new TransferPricingMarkupCalculator;
        $res = $calculator->evaluateMargin(
            method: 'CPM',
            costMinor: 10_000_000,
            actualPriceMinor: 11_200_000,
            benchmarkMarginsBps: [400, 600, 800, 1000, 1200, 1400, 1600]
        );

        $corrupt = $res->certificate;
        $corrupt->sha256 = 'corrupted_tp_hash';

        $this->expectException(InvalidCertificateException::class);
        TransferPricingMarkupCertificateVerifier::assertValid($corrupt);
    }
}
