<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\ContrastCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Ui\WcagContrastCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WcagContrastCalculatorTest extends TestCase
{
    private WcagContrastCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WcagContrastCalculator();
    }

    /**
     * Uji Vektor Wajib Algoritma A1 WcagContrastCalculator
     */
    public function test_wcag_contrast_calculator_vectors(): void
    {
        // 1. #000000 -> L = 0, rasio 210.000
        $res0 = $this->calculator->calculate('#000000', '#FFFFFF');
        $this->assertEquals(0, $res0['luminance_a_ppm']);
        $this->assertEquals(210000, $res0['ratio_bps']);
        $this->assertTrue($res0['passes_aa_normal']);

        // 2. #777777 -> L = 184.475, rasio 44.780 (gagal AA)
        $res1 = $this->calculator->calculate('#777777', '#FFFFFF');
        $this->assertEquals(184475, $res1['luminance_a_ppm']);
        $this->assertEquals(44780, $res1['ratio_bps']);
        $this->assertFalse($res1['passes_aa_normal']);

        // 3. #767676 -> L = 181.164, rasio 45.422 (lulus AA)
        $res2 = $this->calculator->calculate('#767676', '#FFFFFF');
        $this->assertEquals(181164, $res2['luminance_a_ppm']);
        $this->assertEquals(45422, $res2['ratio_bps']);
        $this->assertTrue($res2['passes_aa_normal']);

        // 4. #1D4ED8 -> L = 106.678, rasio 67.016
        $res3 = $this->calculator->calculate('#1D4ED8', '#FFFFFF');
        $this->assertEquals(106678, $res3['luminance_a_ppm']);
        $this->assertEquals(67016, $res3['ratio_bps']);
        $this->assertTrue($res3['passes_aa_normal']);

        // 5. #6B7280 -> L = 167.189, rasio 48.344
        $res4 = $this->calculator->calculate('#6B7280', '#FFFFFF');
        $this->assertEquals(167189, $res4['luminance_a_ppm']);
        $this->assertEquals(48344, $res4['ratio_bps']);
        $this->assertTrue($res4['passes_aa_normal']);

        // 6. #9CA3AF -> L = 363.575, rasio 25.388 (gagal)
        $res5 = $this->calculator->calculate('#9CA3AF', '#FFFFFF');
        $this->assertEquals(363575, $res5['luminance_a_ppm']);
        $this->assertEquals(25388, $res5['ratio_bps']);
        $this->assertFalse($res5['passes_aa_normal']);

        // 7. #FF0000 -> L = 212.600, rasio 39.984 (gagal)
        $res6 = $this->calculator->calculate('#FF0000', '#FFFFFF');
        $this->assertEquals(212600, $res6['luminance_a_ppm']);
        $this->assertEquals(39984, $res6['ratio_bps']);
        $this->assertFalse($res6['passes_aa_normal']);

        // 8. #FFFFFF di atas #FFFFFF -> rasio 10.000
        $res7 = $this->calculator->calculate('#FFFFFF', '#FFFFFF');
        $this->assertEquals(10000, $res7['ratio_bps']);

        // Verifikasi keabsahan sertifikat
        $this->assertTrue(ContrastCertificateVerifier::verify($res2, $res2['certificate']));
    }

    /**
     * Uji Sertifikat Rusak -> Melempar Exception
     */
    public function test_wcag_contrast_broken_certificate_throws_exception(): void
    {
        $res = $this->calculator->calculate('#1D4ED8', '#FFFFFF');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sertifikat kontras warna tidak valid');

        ContrastCertificateVerifier::verify($res, 'sertifikat_palsu_123456');
    }
}

