<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use InvalidArgumentException;
use Modules\Shared\Application\Algorithms\Ui\WcagContrastCalculator;

/**
 * Generator interpolasi palet warna dan ramp swatch CSS tokens.
 */
class ColorPaletteInterpolator
{
    /**
     * Skala pemberat standar Tailwind / Design System kanonikal.
     */
    public const DEFAULT_STOPS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /**
     * Interpolasi ramp warna dari sebuah hex 500 dasar.
     *
     * @param string $baseHex Hex warna dasar (dianggap stop 500)
     * @return array<int, array{stop: int, hex: string, rgb: array{r: int, g: int, b: int}, luminance_ppm: int, contrast_white: float, contrast_black: float, text_color: string}>
     */
    public function generatePalette(string $baseHex): array
    {
        $normalized = $this->normalizeHex($baseHex);
        $baseRgb = $this->hexToRgb($normalized);

        $palette = [];

        foreach (self::DEFAULT_STOPS as $stop) {
            $hex = $this->calculateStopHex($baseRgb, $stop);
            $rgb = $this->hexToRgb($hex);
            $lumPpm = WcagContrastCalculator::relativeLuminancePpm($hex);

            $whiteContrast = WcagContrastCalculator::calculate($hex, '#ffffff');
            $blackContrast = WcagContrastCalculator::calculate($hex, '#000000');

            $contrastWhite = $whiteContrast['ratio_bps'] / 100.0;
            $contrastBlack = $blackContrast['ratio_bps'] / 100.0;

            // Teks kontras optimal: jika kontras terhadap putih >= 4.5 gunakan teks putih, selain itu teks hitam
            $preferredText = $contrastWhite >= 4.5 ? '#ffffff' : '#09090b';

            $palette[$stop] = [
                'stop' => $stop,
                'hex' => $hex,
                'rgb' => $rgb,
                'luminance_ppm' => $lumPpm,
                'contrast_white' => round($contrastWhite, 2),
                'contrast_black' => round($contrastBlack, 2),
                'text_color' => $preferredText,
            ];
        }

        return $palette;
    }

    /**
     * Hitung nilai hex untuk tingkat stop tertentu (50 s/d 950) relatif terhadap stop 500.
     *
     * @param array{r: int, g: int, b: int} $baseRgb
     */
    protected function calculateStopHex(array $baseRgb, int $stop): string
    {
        if ($stop === 500) {
            return $this->rgbToHex($baseRgb['r'], $baseRgb['g'], $baseRgb['b']);
        }

        if ($stop < 500) {
            // Campurkan ke arah putih (#ffffff)
            // Faktor pencampuran: 50 -> 0.95 putih, 400 -> 0.20 putih
            $factor = (500 - $stop) / 500.0 * 0.95;
            $r = (int) round($baseRgb['r'] + (255 - $baseRgb['r']) * $factor);
            $g = (int) round($baseRgb['g'] + (255 - $baseRgb['g']) * $factor);
            $b = (int) round($baseRgb['b'] + (255 - $baseRgb['b']) * $factor);
        } else {
            // Campurkan ke arah hitam (#050505)
            // Faktor pencampuran: 600 -> 0.22 hitam, 950 -> 0.88 hitam
            $factor = ($stop - 500) / 450.0 * 0.85;
            $r = (int) round($baseRgb['r'] * (1.0 - $factor) + 5 * $factor);
            $g = (int) round($baseRgb['g'] * (1.0 - $factor) + 5 * $factor);
            $b = (int) round($baseRgb['b'] * (1.0 - $factor) + 5 * $factor);
        }

        return $this->rgbToHex(
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b))
        );
    }

    /**
     * Konversi R, G, B integer (0-255) ke string Hexadesimal 6-karakter.
     */
    public function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Konversi Hex string (#rrggbb atau #rgb) ke komponen array integer R, G, B.
     *
     * @return array{r: int, g: int, b: int}
     */
    public function hexToRgb(string $hex): array
    {
        $clean = ltrim($hex, '#');

        if (strlen($clean) === 3) {
            $r = hexdec(str_repeat(substr($clean, 0, 1), 2));
            $g = hexdec(str_repeat(substr($clean, 1, 1), 2));
            $b = hexdec(str_repeat(substr($clean, 2, 1), 2));
        } elseif (strlen($clean) === 6) {
            $r = hexdec(substr($clean, 0, 2));
            $g = hexdec(substr($clean, 2, 2));
            $b = hexdec(substr($clean, 4, 2));
        } else {
            throw new InvalidArgumentException("Format string hexadesimal tidak valid: [{$hex}].");
        }

        return [
            'r' => (int) $r,
            'g' => (int) $g,
            'b' => (int) $b,
        ];
    }

    /**
     * Normalisasi string hexadesimal ke format kanonikal 7 karakter (#rrggbb huruf kecil).
     */
    public function normalizeHex(string $hex): string
    {
        $clean = strtolower(ltrim(trim($hex), '#'));

        if (strlen($clean) === 3) {
            $clean = $clean[0] . $clean[0] . $clean[1] . $clean[1] . $clean[2] . $clean[2];
        }

        if (strlen($clean) !== 6 || !ctype_xdigit($clean)) {
            throw new InvalidArgumentException("Nilai warna bukan hexadesimal valid: [{$hex}].");
        }

        return '#' . $clean;
    }

    /**
     * Ekspor palet ke representasi CSS Custom Properties.
     *
     * @param string $prefix Nama prefix CSS token (misal: "color-primary")
     * @return string Blok variabel CSS
     */
    public function toCssVariables(string $prefix, string $baseHex): string
    {
        $palette = $this->generatePalette($baseHex);
        $cleanPrefix = trim(trim($prefix), '-');

        $lines = [];
        foreach ($palette as $stop => $data) {
            $lines[] = "  --ds-{$cleanPrefix}-{$stop}: {$data['hex']};";
            $lines[] = "  --ds-{$cleanPrefix}-{$stop}-contrast: {$data['text_color']};";
        }

        return implode("\n", $lines);
    }
}

