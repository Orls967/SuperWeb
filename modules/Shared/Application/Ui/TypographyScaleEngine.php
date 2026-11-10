<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use InvalidArgumentException;

/**
 * Mesin kalkulasi rasio modular tangga tipografi (Typography Modular Scale) dan CSS tokens.
 */
class TypographyScaleEngine
{
    /**
     * Rasio skala tipografi modular harmonis standar.
     */
    public const RATIOS = [
        'minor_second' => 1.067,
        'major_second' => 1.125,
        'minor_third' => 1.200,
        'major_third' => 1.250,
        'perfect_fourth' => 1.333,
        'augmented_fourth' => 1.414,
        'perfect_fifth' => 1.500,
        'golden_ratio' => 1.618,
    ];

    /**
     * Nama langkah tipografi dari terkecil ke terbesar.
     */
    public const SCALE_STEPS = [
        'xs' => -2,
        'sm' => -1,
        'base' => 0,
        'lg' => 1,
        'xl' => 2,
        '2xl' => 3,
        '3xl' => 4,
        '4xl' => 5,
        '5xl' => 6,
        '6xl' => 7,
    ];

    /**
     * Hitung seluruh tangga skala tipografi dalam satuan REM dan PX.
     *
     * @param float $baseFontSizePx Ukuran font dasar dalam pixel (bawaan: 16.0px)
     * @param string $ratioName Rasio modular yang digunakan (bawaan: 'major_third' = 1.250)
     * @return array<string, array{rem: float, px: float, line_height: float, letter_spacing: string}>
     */
    public function generateScale(float $baseFontSizePx = 16.0, string $ratioName = 'major_third'): array
    {
        if ($baseFontSizePx <= 0) {
            throw new InvalidArgumentException('Ukuran font dasar harus lebih besar dari 0.');
        }

        if (!isset(self::RATIOS[$ratioName])) {
            throw new InvalidArgumentException("Rasio modular [{$ratioName}] tidak dikenal.");
        }

        $ratio = self::RATIOS[$ratioName];
        $scale = [];

        foreach (self::SCALE_STEPS as $name => $stepIndex) {
            $computedPx = $baseFontSizePx * pow($ratio, $stepIndex);
            $computedRem = $computedPx / 16.0;

            // Kalkulasi line-height terbalik: teks besar membutuhkan line-height lebih rapat
            $lineHeight = $stepIndex >= 3 ? 1.2 : ($stepIndex >= 1 ? 1.35 : 1.5);

            // Kalkulasi letter-spacing (tracking): teks besar membutuhkan tracking negatif halus
            $letterSpacing = match (true) {
                $stepIndex >= 4 => '-0.025em',
                $stepIndex >= 2 => '-0.015em',
                $stepIndex <= -1 => '0.025em',
                default => '0em',
            };

            $scale[$name] = [
                'rem' => round($computedRem, 3),
                'px' => round($computedPx, 1),
                'line_height' => $lineHeight,
                'letter_spacing' => $letterSpacing,
            ];
        }

        return $scale;
    }

    /**
     * Ekspor tangga tipografi ke bentuk blok variabel CSS Custom Properties.
     */
    public function toCssVariables(float $baseFontSizePx = 16.0, string $ratioName = 'major_third'): string
    {
        $scale = $this->generateScale($baseFontSizePx, $ratioName);
        $lines = [];

        foreach ($scale as $step => $data) {
            $lines[] = "  --ds-text-{$step}: {$data['rem']}rem;";
            $lines[] = "  --ds-leading-{$step}: {$data['line_height']};";
            $lines[] = "  --ds-tracking-{$step}: {$data['letter_spacing']};";
        }

        return implode("\n", $lines);
    }
}
