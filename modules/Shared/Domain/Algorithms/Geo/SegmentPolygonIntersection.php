<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Geo;

use InvalidArgumentException;

/**
 * Pengujian perpotongan ruas garis dengan INTERIOR poligon berbasis integer eksak.
 * Menyinggung tepi atau sudut poligon dari luar tidak dianggap memotong interior.
 */
final class SegmentPolygonIntersection
{
    private PointInPolygon $pip;

    public function __construct(?PointInPolygon $pip = null)
    {
        $this->pip = $pip ?? new PointInPolygon;
    }

    /**
     * Menguji apakah ruas garis P1 -> P2 memotong INTERIOR poligon.
     *
     * @param  list<array{x: int, y: int}>  $polygon  Titik-titik sudut poligon
     * @param  array{x: int, y: int}  $p1  Titik awal ruas garis
     * @param  array{x: int, y: int}  $p2  Titik akhir ruas garis
     */
    public function intersectsInterior(array $polygon, array $p1, array $p2): bool
    {
        $n = count($polygon);
        if ($n < 3) {
            throw new InvalidArgumentException("Poligon harus memiliki minimal 3 sudut, diberikan: {$n}");
        }

        // 1. Cek apakah ada proper intersection antara ruas P1-P2 dengan salah satu sisi poligon
        for ($i = 0; $i < $n; $i++) {
            $e1 = $polygon[$i];
            $e2 = $polygon[($i + 1) % $n];

            if ($this->hasProperIntersection($p1, $p2, $e1, $e2)) {
                return true;
            }
        }

        // 2. Jika tidak ada proper intersection:
        // Cek apakah salah satu titik ujung atau titik tengah berada strictly inside poligon.
        // Skala 2x untuk mendapatkan titik tengah eksak tanpa pecahan:
        $scaledPolygon = [];
        foreach ($polygon as $v) {
            $scaledPolygon[] = ['x' => $v['x'] * 2, 'y' => $v['y'] * 2];
        }

        $midPoint = [
            'x' => $p1['x'] + $p2['x'],
            'y' => $p1['y'] + $p2['y'],
        ];

        // Jika titik tengah strictly inside (di dalam poligon dan tidak di batas sisi manapun),
        // maka ruas garis melintasi bagian dalam poligon (misal diagonal interior).
        if ($this->isStrictlyInside($scaledPolygon, $midPoint)) {
            return true;
        }

        return false;
    }

    /**
     * Memeriksa apakah dua segmen AB dan CD saling memotong di interior kedua segmen (proper intersection).
     *
     * @param  array{x: int, y: int}  $a
     * @param  array{x: int, y: int}  $b
     * @param  array{x: int, y: int}  $c
     * @param  array{x: int, y: int}  $d
     */
    public function hasProperIntersection(array $a, array $b, array $c, array $d): bool
    {
        $o1 = $this->orientation($a, $b, $c);
        $o2 = $this->orientation($a, $b, $d);
        $o3 = $this->orientation($c, $d, $a);
        $o4 = $this->orientation($c, $d, $b);

        // AB memotong CD secara sejati jika titik C dan D berada di sisi berlawanan dari AB,
        // DAN titik A dan B berada di sisi berlawanan dari CD.
        return (($o1 > 0 && $o2 < 0) || ($o1 < 0 && $o2 > 0))
            && (($o3 > 0 && $o4 < 0) || ($o3 < 0 && $o4 > 0));
    }

    /**
     * Menghitung orientasi 2D (cross product).
     * > 0: CCW (belok kiri)
     * < 0: CW (belok kanan)
     * = 0: Collinear (segaris)
     *
     * @param  array{x: int, y: int}  $a
     * @param  array{x: int, y: int}  $b
     * @param  array{x: int, y: int}  $c
     */
    private function orientation(array $a, array $b, array $c): int
    {
        $cross = ($b['x'] - $a['x']) * ($c['y'] - $a['y']) - ($b['y'] - $a['y']) * ($c['x'] - $a['x']);
        if ($cross > 0) {
            return 1;
        }
        if ($cross < 0) {
            return -1;
        }

        return 0;
    }

    /**
     * Memeriksa apakah titik berada strictly inside poligon (di dalam dan BUKAN pada batas tepi).
     *
     * @param  list<array{x: int, y: int}>  $polygon
     * @param  array{x: int, y: int}  $point
     */
    private function isStrictlyInside(array $polygon, array $point): bool
    {
        $n = count($polygon);
        $px = $point['x'];
        $py = $point['y'];

        // Cek apakah pada batas tepi
        for ($i = 0; $i < $n; $i++) {
            $p1 = $polygon[$i];
            $p2 = $polygon[($i + 1) % $n];

            if ($this->isPointOnSegment($px, $py, $p1['x'], $p1['y'], $p2['x'], $p2['y'])) {
                return false;
            }
        }

        // Ray casting horizontal ke kanan
        $inside = false;
        for ($i = 0; $i < $n; $i++) {
            $p1 = $polygon[$i];
            $p2 = $polygon[($i + 1) % $n];

            $x1 = $p1['x'];
            $y1 = $p1['y'];
            $x2 = $p2['x'];
            $y2 = $p2['y'];

            $yCheck = ($y1 <= $py && $py < $y2) || ($y2 <= $py && $py < $y1);
            if ($yCheck) {
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

    private function isPointOnSegment(int $px, int $py, int $x1, int $y1, int $x2, int $y2): bool
    {
        if (($px === $x1 && $py === $y1) || ($px === $x2 && $py === $y2)) {
            return true;
        }

        $cross = ($x2 - $x1) * ($py - $y1) - ($px - $x1) * ($y2 - $y1);
        if ($cross !== 0) {
            return false;
        }

        $minX = min($x1, $x2);
        $maxX = max($x1, $x2);
        $minY = min($y1, $y2);
        $maxY = max($y1, $y2);

        return $px >= $minX && $px <= $maxX && $py >= $minY && $py <= $maxY;
    }
}
