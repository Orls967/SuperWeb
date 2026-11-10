<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

/**
 * SparklinePath
 *
 * Menghasilkan string jalur koordinat SVG murni <path d="M...">
 * untuk komponen x-ds.sparkline dengan penskalaan vertikal/horisontal presisi.
 */
class SparklinePath
{
    /**
     * Membentuk atribut jalur d SVG dari deret angka integer.
     *
     * @param list<int> $data
     */
    public static function generate(array $data, int $width = 120, int $height = 32, int $padding = 2): string
    {
        $count = count($data);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            $y = (int) round($height / 2);

            return "M {$padding} {$y} L ".($width - $padding)." {$y}";
        }

        $min = min($data);
        $max = max($data);
        $range = max(1, $max - $min);

        $usableW = $width - ($padding * 2);
        $usableH = $height - ($padding * 2);

        $commands = [];
        for ($i = 0; $i < $count; $i++) {
            $x = (int) round($padding + ($i * $usableW / ($count - 1)));
            $normalizedY = ($data[$i] - $min) / $range;
            $y = (int) round($height - $padding - ($normalizedY * $usableH));

            $commands[] = ($i === 0 ? 'M' : 'L')." {$x} {$y}";
        }

        return implode(' ', $commands);
    }
}
