<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\A11yHtmlLinter;
use Modules\Shared\Domain\Algorithms\Ui\A11yLintCertificateVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class A11yHtmlLinterTest extends TestCase
{
    private A11yHtmlLinter $linter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linter = new A11yHtmlLinter();
    }

    /**
     * Uji Vektor Wajib Algoritma A3 A11yHtmlLinter
     */
    public function test_a11y_html_linter_vectors(): void
    {
        $html = "<main>\n".
            "<img src=\"a.png\">\n".
            "<img src=\"b.png\" alt=\"\">\n".
            "<label for=\"n\">Nama</label><input id=\"n\" type=\"text\">\n".
            "<input type=\"text\" name=\"q\">\n".
            "<input type=\"hidden\" name=\"t\">\n".
            "<button></button>\n".
            "<button aria-label=\"Tutup\"></button>\n".
            "<div tabindex=\"2\"></div>\n".
            "<a href=\"/x\"></a>\n".
            "<select id=\"s\"></select>\n".
            '</main>';

        $res = $this->linter->lint($html);

        $expectedViolations = [
            ['line' => 2, 'rule' => A11yHtmlLinter::RULE_IMG_ALT],
            ['line' => 5, 'rule' => A11yHtmlLinter::RULE_INPUT_LABEL],
            ['line' => 7, 'rule' => A11yHtmlLinter::RULE_BUTTON_NAME],
            ['line' => 9, 'rule' => A11yHtmlLinter::RULE_TABINDEX_POSITIVE],
            ['line' => 10, 'rule' => A11yHtmlLinter::RULE_LINK_NAME],
            ['line' => 11, 'rule' => A11yHtmlLinter::RULE_INPUT_LABEL],
        ];

        $this->assertCount(6, $res['violations']);
        $this->assertEquals(6, $res['total_violations']);

        foreach ($expectedViolations as $i => $expected) {
            $this->assertEquals($expected['line'], $res['violations'][$i]['line'], "Mismatch pada indeks {$i} line");
            $this->assertEquals($expected['rule'], $res['violations'][$i]['rule'], "Mismatch pada indeks {$i} rule");
        }

        // Uji HTML Bersih -> menghasilkan 0 pelanggaran
        $cleanHtml = '<main>'."\n".
            '<img src="valid.png" alt="Deskripsi valid">'."\n".
            '<label for="username">Username</label><input id="username" type="text">'."\n".
            '<button type="button">Kirim Form</button>'."\n".
            '<a href="/beranda">Kembali ke Beranda</a>'."\n".
            '</main>';

        $cleanRes = $this->linter->lint($cleanHtml);
        $this->assertCount(0, $cleanRes['violations']);
        $this->assertEquals(0, $cleanRes['total_violations']);

        // Verifikasi keabsahan sertifikat
        $this->assertTrue(A11yLintCertificateVerifier::verify($res, $res['certificate']));
        $this->assertTrue(A11yLintCertificateVerifier::verify($cleanRes, $cleanRes['certificate']));
    }

    /**
     * Uji Sertifikat Rusak -> Melempar Exception
     */
    public function test_a11y_linter_broken_certificate_throws_exception(): void
    {
        $res = $this->linter->lint('<main><img src="test.png"></main>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sertifikat linter aksesibilitas tidak valid');

        A11yLintCertificateVerifier::verify($res, 'sertifikat_rusak_xyz');
    }
}

