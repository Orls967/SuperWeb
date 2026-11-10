<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use InvalidArgumentException;

/**
 * Pengelola dan penilai responsivitas viewport serta target sentuh interaktif (WCAG 2.5.5).
 */
class ResponsiveBreakpointHelper
{
    /**
     * Titik batas breakpoint standar dalam pixel (px).
     */
    public const BREAKPOINTS = [
        'xs' => 0,
        'sm' => 640,
        'md' => 768,
        'lg' => 1024,
        'xl' => 1280,
        '2xl' => 1536,
    ];

    /**
     * Ukuran target sentuh minimum sesuai WCAG 2.5.5 (44px) dan target AA yang direkomendasikan (48px).
     */
    public const MIN_TOUCH_TARGET_PX = 44;

    public const RECOMMENDED_TOUCH_TARGET_PX = 48;

    /**
     * Tentukan nama breakpoint untuk lebar viewport tertentu dalam pixel.
     */
    public function getBreakpointName(int $viewportWidthPx): string
    {
        if ($viewportWidthPx < 0) {
            throw new InvalidArgumentException("Lebar viewport tidak boleh negatif: [{$viewportWidthPx}].");
        }

        if ($viewportWidthPx >= self::BREAKPOINTS['2xl']) {
            return '2xl';
        }
        if ($viewportWidthPx >= self::BREAKPOINTS['xl']) {
            return 'xl';
        }
        if ($viewportWidthPx >= self::BREAKPOINTS['lg']) {
            return 'lg';
        }
        if ($viewportWidthPx >= self::BREAKPOINTS['md']) {
            return 'md';
        }
        if ($viewportWidthPx >= self::BREAKPOINTS['sm']) {
            return 'sm';
        }

        return 'xs';
    }

    /**
     * Periksa apakah lebar viewport termasuk perangkat bergerak (layar kecil).
     */
    public function isMobile(int $viewportWidthPx): bool
    {
        return $viewportWidthPx < self::BREAKPOINTS['md'];
    }

    /**
     * Periksa apakah lebar viewport termasuk tablet.
     */
    public function isTablet(int $viewportWidthPx): bool
    {
        return $viewportWidthPx >= self::BREAKPOINTS['md'] && $viewportWidthPx < self::BREAKPOINTS['lg'];
    }

    /**
     * Periksa apakah lebar viewport termasuk desktop/layar besar.
     */
    public function isDesktop(int $viewportWidthPx): bool
    {
        return $viewportWidthPx >= self::BREAKPOINTS['lg'];
    }

    /**
     * Evaluasi kecukupan target sentuh tombol/kontrol interaktif (WCAG 2.5.5).
     *
     * @return array{compliant: bool, width_ok: bool, height_ok: bool, deficit_width_px: int, deficit_height_px: int, message: string}
     */
    public function evaluateTouchTarget(int $widthPx, int $heightPx): array
    {
        $widthOk = $widthPx >= self::MIN_TOUCH_TARGET_PX;
        $heightOk = $heightPx >= self::MIN_TOUCH_TARGET_PX;
        $compliant = $widthOk && $heightOk;

        $deficitW = max(0, self::MIN_TOUCH_TARGET_PX - $widthPx);
        $deficitH = max(0, self::MIN_TOUCH_TARGET_PX - $heightPx);

        $msg = $compliant
            ? "Target sentuh {$widthPx}x{$heightPx}px memenuhi standar WCAG (>=44x44px)."
            : "Target sentuh {$widthPx}x{$heightPx}px tidak mencukupi; butuh tambahan {$deficitW}px lebar dan {$deficitH}px tinggi.";

        return [
            'compliant' => $compliant,
            'width_ok' => $widthOk,
            'height_ok' => $heightOk,
            'deficit_width_px' => $deficitW,
            'deficit_height_px' => $deficitH,
            'message' => $msg,
        ];
    }

    /**
     * Hitung ekspresi CSS clamp() fluida antara ukuran minimum dan maksimum berdasarkan viewport.
     *
     * @param  float  $minRem  Ukuran terkecil pada viewport terkecil (rem)
     * @param  float  $maxRem  Ukuran terbesar pada viewport terbesar (rem)
     * @param  int  $minVwPx  Lebar viewport minimum (px), bawaan 375px
     * @param  int  $maxVwPx  Lebar viewport maksimum (px), bawaan 1280px
     * @return string Ekspresi CSS clamp(...)
     */
    public function calculateFluidClamp(
        float $minRem,
        float $maxRem,
        int $minVwPx = 375,
        int $maxVwPx = 1280
    ): string {
        if ($maxVwPx <= $minVwPx) {
            throw new InvalidArgumentException('Viewport maksimum harus lebih besar dari viewport minimum.');
        }

        $minPx = $minRem * 16.0;
        $maxPx = $maxRem * 16.0;

        $slope = ($maxPx - $minPx) / ($maxVwPx - $minVwPx);
        $yIntersection = (-$minVwPx * $slope) + $minPx;

        $slopeVw = round($slope * 100, 3);
        $interceptRem = round($yIntersection / 16.0, 3);

        return sprintf(
            'clamp(%.3frem, %.3frem + %.3fvw, %.3frem)',
            $minRem,
            $interceptRem,
            $slopeVw,
            $maxRem
        );
    }

    /**
     * Susun aturan CSS @media query untuk rentang breakpoint tertentu.
     */
    public function buildMediaQuery(string $breakpoint, string $direction = 'min'): string
    {
        if (! isset(self::BREAKPOINTS[$breakpoint])) {
            throw new InvalidArgumentException("Breakpoint [{$breakpoint}] tidak dikenal.");
        }

        $px = self::BREAKPOINTS[$breakpoint];

        if ($direction === 'max') {
            $maxPx = max(0, $px - 1);

            return "@media (max-width: {$maxPx}px)";
        }

        return "@media (min-width: {$px}px)";
    }
}
