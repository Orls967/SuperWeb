<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Geo;

use InvalidArgumentException;

/**
 * Algoritma pengujian titik di dalam poligon (Point-In-Polygon) berbasis Ray Casting dan Cross Product integer murni.
 * Koordinat dalam mikroderajat (derajat * 1e6, misal -180_000_000 s.d. 180_000_000).
 *
 * Bukti tidak ada overflow 64-bit integer:
 * |Δx| <= 360_000_000, |Δy| <= 180_000_000.
 * Hasil kali silang maksimum: 3.6e8 * 1.8e8 = 6.48e16.
 * Selisih dua perkalian <= 1.3e17 << PHP_INT_MAX (9.22e18).
 *
 * Titik di batas (tepi atau sudut) dihitung SEBAGAI DI DALAM poligon.
 * Kompleksitas: O(N) di mana N adalah jumlah titik sudut poligon.
 */
final class PointInPolygon
{
    public const int MAX_COORD = 180_000_000;

    public const int MIN_COORD = -180_000_000;

    /**
     * Memeriksa apakah titik $point berada di dalam atau pada batas $polygon.
     *
     * @param  list<array{x: int, y: int}>  $polygon  Daftar titik poligon (minimal 3)
     * @param  array{x: int, y: int}  $point  Titik yang diuji
     */
    public function contains(array $polygon, array $point): bool
    {
        $n = count($polygon);
        if ($n < 3) {
            throw new InvalidArgumentException("Poligon harus memiliki minimal 3 titik sudut, diberikan: {$n}");
        }

        $this->validateCoord($point['x'], $point['y']);
        foreach ($polygon as $vertex) {
            $this->validateCoord($vertex['x'], $vertex['y']);
        }

        $px = $point['x'];
        $py = $point['y'];

        // 1. Cek apakah titik persis pada batas (tepi atau titik sudut)
        for ($i = 0; $i < $n; $i++) {
            $p1 = $polygon[$i];
            $p2 = $polygon[($i + 1) % $n];

            if ($this->isPointOnSegment($px, $py, $p1['x'], $p1['y'], $p2['x'], $p2['y'])) {
                return true;
            }
        }

        // 2. Ray casting ke arah kanan (+X): hitung jumlah perpotongan garis horizontal
        $inside = false;
        for ($i = 0; $i < $n; $i++) {
            $p1 = $polygon[$i];
            $p2 = $polygon[($i + 1) % $n];

            $x1 = $p1['x'];
            $y1 = $p1['y'];
            $x2 = $p2['x'];
            $y2 = $p2['y'];

            // Garis tepi memotong sinar horizontal bila satu titik di bawah atau sama dengan py,
            // dan titik lainnya di atas py: (y1 <= py < y2) atau (y2 <= py < y1)
            $yCheck = ($y1 <= $py && $py < $y2) || ($y2 <= $py && $py < $y1);
            if ($yCheck) {
                // Cross product untuk menguji apakah sinar (+X dari P) memotong segmen P1-P2
                // Sisi P1->P2 berpotongan dengan ray P ke kanan bila x_interseksi > px
                $cross = ($x2 - $x1) * ($py - $y1) - ($px - $x1) * ($y2 - $y1);

                if ($y1 < $y2) {
                    if ($cross > 0) {
                        $inside = ! $inside;
                    }
                } else {
                    if ($cross < 0) {
                        $inside = ! $inside;
                    }
                }
            }
        }

        return $inside;
    }

    /**
     * Memeriksa apakah titik (px, py) terletak pada ruas garis antara (x1, y1) dan (x2, y2).
     */
    private function isPointOnSegment(int $px, int $py, int $x1, int $y1, int $x2, int $y2): bool
    {
        // Titik sudut langsung
        if (($px === $x1 && $py === $y1) || ($px === $x2 && $py === $y2)) {
            return true;
        }

        // Bounding box ruas garis
        if ($px < min($x1, $x2) || $px > max($x1, $x2) || $py < min($y1, $y2) || $py > max($y1, $y2)) {
            return false;
        }

        // Cross product untuk kolinearitas: (x2 - x1)*(py - y1) - (y2 - y1)*(px - x1) == 0
        $cross = ($x2 - $x1) * ($py - $y1) - ($y2 - $y1) * ($px - $x1);

        return $cross === 0;
    }

    private function validateCoord(int $x, int $y): void
    {
        if ($x < self::MIN_COORD || $x > self::MAX_COORD) {
            throw new InvalidArgumentException("Koordinat x di luar rentang: {$x}");
        }
        if ($y < self::MIN_COORD || $y > self::MAX_COORD) {
            throw new InvalidArgumentException("Koordinat y di luar rentang: {$y}");
        }
    }
}
