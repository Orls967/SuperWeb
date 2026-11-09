<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Algoritma Indeks Kemudahan Pemeliharaan Perangkat Lunak (Software Maintainability Index - MI).
 * Mengimplementasikan formula SEI/Microsoft berbasis Halstead Volume, Cyclomatic Complexity, dan LOC.
 */
final class SoftwareMaintainabilityIndex
{
    /**
     * Menghitung nilai Maintainability Index (skala 0 - 100)
     *
     * @param float $halsteadVolume Nilai V dari Halstead Metrics
     * @param int $cyclomaticComplexity Nilai M (McCabe)
     * @param int $linesOfCode Jumlah baris kode eksekutabel
     * @return array{maintainability_index: float, rating: string, color: string}
     */
    public function calculate(float $halsteadVolume, int $cyclomaticComplexity, int $linesOfCode): array
    {
        $v = max(1.0, $halsteadVolume);
        $g = max(1, $cyclomaticComplexity);
        $loc = max(1, $linesOfCode);

        // Rumus SEI Standar: 171 - 5.2 * ln(V) - 0.23 * G - 16.2 * ln(LOC)
        $rawMi = 171.0 - (5.2 * log($v)) - (0.23 * $g) - (16.2 * log($loc));

        // Normalisasi Microsoft skala 0 - 100
        $normalizedMi = max(0.0, ($rawMi * 100.0) / 171.0);
        $mi = round(min(100.0, $normalizedMi), 2);

        $rating = match (true) {
            $mi >= 85.0 => 'excellent',
            $mi >= 65.0 => 'good',
            $mi >= 50.0 => 'moderate',
            default => 'poor_refactor_needed',
        };

        $color = match ($rating) {
            'excellent' => '#15803d',
            'good' => '#0284c7',
            'moderate' => '#d97706',
            default => '#dc2626',
        };

        return [
            'maintainability_index' => $mi,
            'rating' => $rating,
            'color' => $color,
        ];
    }
}

