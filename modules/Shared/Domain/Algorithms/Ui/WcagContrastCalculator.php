<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use InvalidArgumentException;

/**
 * WcagContrastCalculator (Algoritma A1 — Fase 240)
 *
 * Menghitung luminansi relatif sRGB dan rasio kontras warna presisi tinggi WCAG 2.1
 * murni menggunakan aritmetika integer (parts-per-million dan basis points).
 * Float HANYA digunakan untuk fungsi daya gamma sRGB pow(x, 2.4) yang hasilnya dibulatkan HalfUp ke ppm.
 *
 * Kompleksitas Waktu: O(1)
 * Kompleksitas Ruang: O(1)
 */
class WcagContrastCalculator
{
    public const THRESHOLD_AA_NORMAL = 45000;   // 4.5:1

    public const THRESHOLD_AA_LARGE = 30000;    // 3.0:1

    public const THRESHOLD_UI_COMPONENT = 30000; // 3.0:1

    public const THRESHOLD_AAA_NORMAL = 70000;  // 7.0:1

    /**
     * Menghitung rasio kontras warna dan menerbitkan sertifikat matematis.
     *
     * @param  string  $hexColorA  Format #RRGGBB atau RRGGBB
     * @param  string  $hexColorB  Format #RRGGBB atau RRGGBB
     * @return array{
     *   color_a: string,
     *   color_b: string,
     *   luminance_a_ppm: int,
     *   luminance_b_ppm: int,
     *   ratio_bps: int,
     *   passes_aa_normal: bool,
     *   passes_aa_large: bool,
     *   passes_ui: bool,
     *   passes_aaa_normal: bool,
     *   certificate: string
     * }
     */
    public function calculate(string $hexColorA, string $hexColorB): array
    {
        $cleanA = $this->normalizeHex($hexColorA);
        $cleanB = $this->normalizeHex($hexColorB);

        $lumA = $this->relativeLuminancePpm($cleanA);
        $lumB = $this->relativeLuminancePpm($cleanB);

        $bright = max($lumA, $lumB);
        $dark = min($lumA, $lumB);

        // Formula WCAG: (L_terang + 0.05) / (L_gelap + 0.05)
        // 0.05 setara 50.000 ppm. Rasio dalam bps (x 10.000, floor).
        $numerator = ($bright + 50000) * 10000;
        $denominator = $dark + 50000;
        $ratioBps = (int) floor($numerator / $denominator);

        $passesAaNormal = $ratioBps >= self::THRESHOLD_AA_NORMAL;
        $passesAaLarge = $ratioBps >= self::THRESHOLD_AA_LARGE;
        $passesUi = $ratioBps >= self::THRESHOLD_UI_COMPONENT;
        $passesAaaNormal = $ratioBps >= self::THRESHOLD_AAA_NORMAL;

        $payload = [
            'color_a' => $cleanA,
            'color_b' => $cleanB,
            'luminance_a_ppm' => $lumA,
            'luminance_b_ppm' => $lumB,
            'ratio_bps' => $ratioBps,
            'passes_aa_normal' => $passesAaNormal,
            'passes_aa_large' => $passesAaLarge,
            'passes_ui' => $passesUi,
            'passes_aaa_normal' => $passesAaaNormal,
        ];

        $payload['certificate'] = ContrastCertificateVerifier::sign($payload);

        return $payload;
    }

    /**
     * Menghitung nilai luminansi relatif sRGB dalam ppm (integer 0 s/d 1.000.000).
     */
    public function relativeLuminancePpm(string $hexColor): int
    {
        $hex = $this->normalizeHex($hexColor);
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));

        $linR = $this->linearizeChannel((int) $r);
        $linG = $this->linearizeChannel((int) $g);
        $linB = $this->linearizeChannel((int) $b);

        // L = (2126*R + 7152*G + 722*B + 5000) / 10000 (floor)
        return (int) floor((2126 * $linR + 7152 * $linG + 722 * $linB + 5000) / 10000);
    }

    /**
     * Linearisasi kanal sRGB 8-bit ke bilangan bulat ppm (0..1.000.000).
     * Float hanya digunakan pada pow(x, 2.4), dibulatkan HalfUp ke integer ppm.
     */
    private function linearizeChannel(int $channel): int
    {
        if ($channel < 0 || $channel > 255) {
            throw new InvalidArgumentException("Kanal warna harus di antara 0 dan 255: {$channel}");
        }

        $cNorm = $channel / 255.0;
        if ($cNorm <= 0.04045) {
            $val = ($cNorm / 12.92) * 1000000.0;
        } else {
            $val = pow(($cNorm + 0.055) / 1.055, 2.4) * 1000000.0;
        }

        return (int) round($val);
    }

    /**
     * Normalisasi format heksadesimal ke #RRGGBB huruf besar.
     */
    private function normalizeHex(string $hex): string
    {
        $clean = strtoupper(trim($hex));
        if (str_starts_with($clean, '#')) {
            $clean = substr($clean, 1);
        }

        if (strlen($clean) === 3) {
            $clean = $clean[0].$clean[0].$clean[1].$clean[1].$clean[2].$clean[2];
        }

        if (strlen($clean) !== 6 || ! ctype_xdigit($clean)) {
            throw new InvalidArgumentException("Format heksadesimal warna tidak valid: {$hex}");
        }

        return '#'.$clean;
    }
}
