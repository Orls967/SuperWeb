<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\ThemeTokenResolver;
use Modules\Shared\Domain\Algorithms\Ui\TokenResolutionCertificateVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ThemeTokenResolverTest extends TestCase
{
    private ThemeTokenResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ThemeTokenResolver;
    }

    /**
     * Uji Vektor Wajib Algoritma A2 ThemeTokenResolver
     */
    public function test_theme_token_resolver_vectors(): void
    {
        $themeDefinitions = [
            'light' => [
                'bg' => '#FFFFFF',
                'text' => '#111827',
                'primary' => '#1D4ED8',
                'onPrimary' => '{bg}',
                'muted' => '#9CA3AF',
                'danger' => '#B91C1C',
            ],
            'dark' => [
                'bg' => '#0F172A',
                'text' => '#F1F5F9',
                'primary' => '#93C5FD',
                'muted' => '#94A3B8',
            ],
            'hc' => [
                'bg' => '#000000',
                'text' => '#FFFFFF',
                'muted' => '{text}',
            ],
        ];

        // 1. Resolusi Tema Light
        $resLight = $this->resolver->resolve($themeDefinitions, 'light');
        $this->assertEquals(ThemeTokenResolver::STATUS_RESOLVED, $resLight['status']);
        $this->assertEquals('#FFFFFF', $resLight['tokens']['bg']);
        $this->assertEquals('#111827', $resLight['tokens']['text']);
        $this->assertEquals('#1D4ED8', $resLight['tokens']['primary']);
        $this->assertEquals('#FFFFFF', $resLight['tokens']['onPrimary']);
        $this->assertEquals('#9CA3AF', $resLight['tokens']['muted']);
        $this->assertEquals('#B91C1C', $resLight['tokens']['danger']);

        $this->assertEquals(177397, $resLight['contrast_pairs']['text/bg']['ratio_bps']);
        $this->assertTrue($resLight['contrast_pairs']['text/bg']['passes_aa']);
        $this->assertEquals(67016, $resLight['contrast_pairs']['onPrimary/primary']['ratio_bps']);
        $this->assertTrue($resLight['contrast_pairs']['onPrimary/primary']['passes_aa']);
        $this->assertEquals(25388, $resLight['contrast_pairs']['muted/bg']['ratio_bps']);
        $this->assertFalse($resLight['contrast_pairs']['muted/bg']['passes_aa']); // Gagal AA

        // 2. Resolusi Tema Dark
        $resDark = $this->resolver->resolve($themeDefinitions, 'dark');
        $this->assertEquals(ThemeTokenResolver::STATUS_RESOLVED, $resDark['status']);
        $this->assertEquals('#0F172A', $resDark['tokens']['bg']);
        $this->assertEquals('#F1F5F9', $resDark['tokens']['text']);
        $this->assertEquals('#93C5FD', $resDark['tokens']['primary']);
        $this->assertEquals('#0F172A', $resDark['tokens']['onPrimary']);
        $this->assertEquals('#94A3B8', $resDark['tokens']['muted']);
        $this->assertEquals('#B91C1C', $resDark['tokens']['danger']);

        $this->assertEquals(162960, $resDark['contrast_pairs']['text/bg']['ratio_bps']);
        $this->assertTrue($resDark['contrast_pairs']['text/bg']['passes_aa']);
        $this->assertEquals(99000, $resDark['contrast_pairs']['onPrimary/primary']['ratio_bps']);
        $this->assertTrue($resDark['contrast_pairs']['onPrimary/primary']['passes_aa']);
        $this->assertEquals(69626, $resDark['contrast_pairs']['muted/bg']['ratio_bps']);
        $this->assertTrue($resDark['contrast_pairs']['muted/bg']['passes_aa']);

        // 3. Resolusi Tema HC (Kontras Tinggi)
        $resHc = $this->resolver->resolve($themeDefinitions, 'hc');
        $this->assertEquals(ThemeTokenResolver::STATUS_RESOLVED, $resHc['status']);
        $this->assertEquals('#000000', $resHc['tokens']['bg']);
        $this->assertEquals('#FFFFFF', $resHc['tokens']['text']);
        $this->assertEquals('#93C5FD', $resHc['tokens']['primary']);
        $this->assertEquals('#000000', $resHc['tokens']['onPrimary']);
        $this->assertEquals('#FFFFFF', $resHc['tokens']['muted']);
        $this->assertEquals('#B91C1C', $resHc['tokens']['danger']);

        $this->assertEquals(210000, $resHc['contrast_pairs']['text/bg']['ratio_bps']);
        $this->assertTrue($resHc['contrast_pairs']['text/bg']['passes_aa']);
        $this->assertEquals(116454, $resHc['contrast_pairs']['onPrimary/primary']['ratio_bps']);
        $this->assertTrue($resHc['contrast_pairs']['onPrimary/primary']['passes_aa']);
        $this->assertEquals(210000, $resHc['contrast_pairs']['muted/bg']['ratio_bps']);
        $this->assertTrue($resHc['contrast_pairs']['muted/bg']['passes_aa']);

        // 4. Deteksi Siklus Token (TOKEN_CYCLE)
        $cycleDefs = [
            'light' => [
                'a' => '{b}',
                'b' => '{a}',
            ],
        ];
        $resCycle = $this->resolver->resolve($cycleDefs, 'light');
        $this->assertEquals(ThemeTokenResolver::STATUS_CYCLE, $resCycle['status']);

        // 5. Deteksi Token Tak Terdefinisi (TOKEN_UNDEFINED)
        $undefDefs = [
            'light' => [
                'x' => '{zzz}',
            ],
        ];
        $resUndef = $this->resolver->resolve($undefDefs, 'light');
        $this->assertEquals(ThemeTokenResolver::STATUS_UNDEFINED, $resUndef['status']);

        // Verifikasi keabsahan sertifikat
        $this->assertTrue(TokenResolutionCertificateVerifier::verify($resHc, $resHc['certificate']));
    }

    /**
     * Uji Sertifikat Rusak -> Melempar Exception
     */
    public function test_theme_token_broken_certificate_throws_exception(): void
    {
        $themeDefs = [
            'light' => ['bg' => '#FFFFFF'],
        ];
        $res = $this->resolver->resolve($themeDefs, 'light');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sertifikat resolusi token tidak valid');

        TokenResolutionCertificateVerifier::verify($res, 'sertifikat_korup_abc');
    }
}
