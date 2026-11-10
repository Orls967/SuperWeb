<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

/**
 * SvgChartGeometry
 *
 * Menghitung skala sumbu, titik koordinat, garis grid, dan jalur kurva/batang SVG
 * secara server-side murni menggunakan kalkulasi matematika PHP tanpa dependensi JS.
 */
class SvgChartGeometry
{
    /**
     * Menghitung skala rentang "bagus" (nice ticks) berbasis bilangan bulat.
     *
     * @param list<int> $values
     * @return array{min: int, max: int, step: int, ticks: list<int>}
     */
    public static function calculateNiceScale(array $values, int $targetTickCount = 5): array
    {
        if (empty($values)) {
            return ['min' => 0, 'max' => 100, 'step' => 20, 'ticks' => [0, 20, 40, 60, 80, 100]];
        }

        $min = min($values);
        $max = max($values);

        if ($min === $max) {
            $min = max(0, $min - 10);
            $max = $max + 10;
        }

        $range = $max - $min;
        $roughStep = max(1, (int) ceil($range / $targetTickCount));

        // Normalisasi ke kelipatan terdekat: 1, 2, 5, 10, 20, 50, 100, 500, 1000...
        $magnitude = (int) pow(10, (int) floor(log10((float) $roughStep)));
        $fraction = $roughStep / $magnitude;

        if ($fraction <= 1.0) {
            $step = $magnitude;
        } elseif ($fraction <= 2.0) {
            $step = 2 * $magnitude;
        } elseif ($fraction <= 5.0) {
            $step = 5 * $magnitude;
        } else {
            $step = 10 * $magnitude;
        }

        $niceMin = (int) floor($min / $step) * $step;
        $niceMax = (int) ceil($max / $step) * $step;

        $ticks = [];
        for ($val = $niceMin; $val <= $niceMax; $val += $step) {
            $ticks[] = $val;
        }

        return [
            'min' => $niceMin,
            'max' => $niceMax,
            'step' => $step,
            'ticks' => $ticks,
        ];
    }

    /**
     * Memetakan deret nilai ke koordinat titik garis SVG (x, y).
     *
     * @param list<int> $values
     * @return array{
     *   points: list<array{x: int, y: int, value: int, label: string}>,
     *   path_d: string,
     *   area_d: string,
     *   scale: array{min: int, max: int, step: int, ticks: list<int>}
     * }
     */
    public static function buildLineChart(
        array $values,
        array $labels,
        int $width = 600,
        int $height = 240,
        int $paddingX = 40,
        int $paddingY = 30
    ): array {
        $count = count($values);
        $scale = self::calculateNiceScale($values);
        $range = max(1, $scale['max'] - $scale['min']);

        $usableWidth = $width - ($paddingX * 2);
        $usableHeight = $height - ($paddingY * 2);

        $points = [];
        $pathCommands = [];

        for ($i = 0; $i < $count; $i++) {
            $val = $values[$i];
            $lbl = $labels[$i] ?? (string) ($i + 1);

            $x = $count > 1
                ? (int) round($paddingX + ($i * $usableWidth / ($count - 1)))
                : (int) round($width / 2);

            $normalizedY = ($val - $scale['min']) / $range;
            $y = (int) round($height - $paddingY - ($normalizedY * $usableHeight));

            $points[] = [
                'x' => $x,
                'y' => $y,
                'value' => $val,
                'label' => $lbl,
            ];

            $pathCommands[] = ($i === 0 ? 'M' : 'L')." {$x} {$y}";
        }

        $pathD = implode(' ', $pathCommands);

        // Area fill tertutup ke sumbu bawah
        $baselineY = $height - $paddingY;
        $areaD = '';
        if (! empty($points)) {
            $firstX = $points[0]['x'];
            $lastX = $points[count($points) - 1]['x'];
            $areaD = "{$pathD} L {$lastX} {$baselineY} L {$firstX} {$baselineY} Z";
        }

        return [
            'points' => $points,
            'path_d' => $pathD,
            'area_d' => $areaD,
            'scale' => $scale,
        ];
    }

    /**
     * Memetakan deret nilai ke geometri balok diagram batang SVG.
     *
     * @param list<int> $values
     * @return array{
     *   bars: list<array{x: int, y: int, width: int, height: int, value: int, label: string}>,
     *   scale: array{min: int, max: int, step: int, ticks: list<int>}
     * }
     */
    public static function buildBarChart(
        array $values,
        array $labels,
        int $width = 600,
        int $height = 240,
        int $paddingX = 40,
        int $paddingY = 30,
        int $barGap = 12
    ): array {
        $count = count($values);
        $scale = self::calculateNiceScale($values);
        $range = max(1, $scale['max'] - $scale['min']);

        $usableWidth = $width - ($paddingX * 2);
        $usableHeight = $height - ($paddingY * 2);
        $baselineY = $height - $paddingY;

        $barWidth = max(4, (int) floor(($usableWidth - (($count - 1) * $barGap)) / max(1, $count)));

        $bars = [];
        for ($i = 0; $i < $count; $i++) {
            $val = $values[$i];
            $lbl = $labels[$i] ?? (string) ($i + 1);

            $x = $paddingX + ($i * ($barWidth + $barGap));
            $normalizedH = max(0, $val - $scale['min']) / $range;
            $barH = max(2, (int) round($normalizedH * $usableHeight));
            $y = $baselineY - $barH;

            $bars[] = [
                'x' => $x,
                'y' => $y,
                'width' => $barWidth,
                'height' => $barH,
                'value' => $val,
                'label' => $lbl,
            ];
        }

        return [
            'bars' => $bars,
            'scale' => $scale,
        ];
    }
}
