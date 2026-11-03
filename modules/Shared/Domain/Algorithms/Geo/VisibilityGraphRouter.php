<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Geo;

use InvalidArgumentException;

/**
 * Visibility Graph Router + Dijkstra untuk penghindaran zona larangan terbang (no-fly zone) drone.
 * Mematuhi invarian D80-4: tie-break leksikografis (x, y) terkecil, pembulatan floor ke meter, sertifikat kepatuhan.
 */
final class VisibilityGraphRouter
{
    private SegmentPolygonIntersection $intersection;

    public function __construct(?SegmentPolygonIntersection $intersection = null)
    {
        $this->intersection = $intersection ?? new SegmentPolygonIntersection;
    }

    /**
     * Menghitung jalur terpendek dari Hub ke Tujuan dengan menghindari interior zona poligon.
     *
     * @param  array{x: int, y: int}  $hub  Titik awal (Hub)
     * @param  array{x: int, y: int}  $destination  Titik tujuan
     * @param  list<list<array{x: int, y: int}>>  $noFlyZones  Daftar poligon larangan terbang
     * @param  int  $maxBatteryRangeMeters  Jangkauan maksimum baterai drone (dalam meter)
     * @return array{
     *     path: list<array{x: int, y: int}>,
     *     length_meters: int,
     *     exact_length_meters: float,
     *     certificate: array{
     *         is_valid: bool,
     *         segments_valid: bool,
     *         length_matches: bool,
     *         within_range: bool,
     *         error: ?string
     *     }
     * }
     */
    public function findRoute(
        array $hub,
        array $destination,
        array $noFlyZones,
        int $maxBatteryRangeMeters
    ): array {
        // 1. Cek jalur lurus langsung Hub -> Tujuan
        $directSafe = true;
        foreach ($noFlyZones as $zone) {
            if ($this->intersection->intersectsInterior($zone, $hub, $destination)) {
                $directSafe = false;
                break;
            }
        }

        if ($directSafe) {
            $dx = $destination['x'] - $hub['x'];
            $dy = $destination['y'] - $hub['y'];
            $exactLen = sqrt($dx * $dx + $dy * $dy);
            $floorLen = (int) floor($exactLen);

            $result = [
                'path' => [$hub, $destination],
                'length_meters' => $floorLen,
                'exact_length_meters' => $exactLen,
                'certificate' => [
                    'is_valid' => $floorLen <= $maxBatteryRangeMeters,
                    'segments_valid' => true,
                    'length_matches' => true,
                    'within_range' => $floorLen <= $maxBatteryRangeMeters,
                    'error' => $floorLen > $maxBatteryRangeMeters ? 'Panjang jalur melebihi jangkauan baterai' : null,
                ],
            ];

            return $result;
        }

        // 2. Bangun daftar simpul unik: Hub, Destination, dan semua simpul no-fly zone
        /** @var array<string, array{x: int, y: int}> $uniqueNodes */
        $uniqueNodes = [];
        $uniqueNodes[$this->nodeKey($hub)] = $hub;
        $uniqueNodes[$this->nodeKey($destination)] = $destination;

        foreach ($noFlyZones as $zone) {
            foreach ($zone as $v) {
                $uniqueNodes[$this->nodeKey($v)] = $v;
            }
        }

        /** @var list<array{x: int, y: int}> $nodes */
        $nodes = array_values($uniqueNodes);
        $nodeCount = count($nodes);

        // 3. Bangun edge visibilitas
        /** @var array<string, list<array{key: string, node: array{x: int, y: int}, weight: float}>> $adj */
        $adj = [];
        foreach ($nodes as $node) {
            $adj[$this->nodeKey($node)] = [];
        }

        for ($i = 0; $i < $nodeCount; $i++) {
            for ($j = $i + 1; $j < $nodeCount; $j++) {
                $u = $nodes[$i];
                $v = $nodes[$j];

                $safe = true;
                foreach ($noFlyZones as $zone) {
                    if ($this->intersection->intersectsInterior($zone, $u, $v)) {
                        $safe = false;
                        break;
                    }
                }

                if ($safe) {
                    $dx = $v['x'] - $u['x'];
                    $dy = $v['y'] - $u['y'];
                    $weight = sqrt($dx * $dx + $dy * $dy);

                    $uKey = $this->nodeKey($u);
                    $vKey = $this->nodeKey($v);

                    $adj[$uKey][] = ['key' => $vKey, 'node' => $v, 'weight' => $weight];
                    $adj[$vKey][] = ['key' => $uKey, 'node' => $u, 'weight' => $weight];
                }
            }
        }

        // 4. Dijkstra dengan tie-break urutan simpul leksikografis terkecil
        $startKey = $this->nodeKey($hub);
        $targetKey = $this->nodeKey($destination);

        /** @var array<string, float> $dist */
        $dist = [];
        /** @var array<string, list<array{x: int, y: int}>> $paths */
        $paths = [];

        foreach ($nodes as $node) {
            $k = $this->nodeKey($node);
            $dist[$k] = INF;
            $paths[$k] = [];
        }

        $dist[$startKey] = 0.0;
        $paths[$startKey] = [$hub];

        /** @var array<string, bool> $visited */
        $visited = [];

        while (true) {
            $bestKey = null;
            $bestDist = INF;
            $bestPath = null;

            foreach ($nodes as $node) {
                $k = $this->nodeKey($node);
                if (isset($visited[$k])) {
                    continue;
                }

                $d = $dist[$k];
                if ($d === INF) {
                    continue;
                }

                if ($bestKey === null || $d < ($bestDist - 1e-9)) {
                    $bestKey = $k;
                    $bestDist = $d;
                    $bestPath = $paths[$k];
                } elseif (abs($d - $bestDist) <= 1e-9) {
                    // Tie-break: bandingkan path leksikografis
                    if ($this->comparePaths($paths[$k], $bestPath) < 0) {
                        $bestKey = $k;
                        $bestDist = $d;
                        $bestPath = $paths[$k];
                    }
                }
            }

            if ($bestKey === null || $bestKey === $targetKey) {
                break;
            }

            $visited[$bestKey] = true;

            foreach ($adj[$bestKey] as $edge) {
                $neighborKey = $edge['key'];
                if (isset($visited[$neighborKey])) {
                    continue;
                }

                $newDist = $dist[$bestKey] + $edge['weight'];
                $newPath = array_merge($paths[$bestKey], [$edge['node']]);

                if ($newDist < ($dist[$neighborKey] - 1e-9)) {
                    $dist[$neighborKey] = $newDist;
                    $paths[$neighborKey] = $newPath;
                } elseif (abs($newDist - $dist[$neighborKey]) <= 1e-9) {
                    if ($this->comparePaths($newPath, $paths[$neighborKey]) < 0) {
                        $dist[$neighborKey] = $newDist;
                        $paths[$neighborKey] = $newPath;
                    }
                }
            }
        }

        if ($dist[$targetKey] === INF) {
            throw new InvalidArgumentException('Tidak ditemukan jalur yang aman dari no-fly zone menuju tujuan');
        }

        $exactLength = $dist[$targetKey];
        $floorLength = (int) floor($exactLength);
        $finalPath = $paths[$targetKey];

        $withinRange = $floorLength <= $maxBatteryRangeMeters;
        $certificate = [
            'is_valid' => $withinRange,
            'segments_valid' => true,
            'length_matches' => true,
            'within_range' => $withinRange,
            'error' => ! $withinRange ? "Jarak rute ({$floorLength}m) melebihi jangkauan baterai ({$maxBatteryRangeMeters}m)" : null,
        ];

        return [
            'path' => $finalPath,
            'length_meters' => $floorLength,
            'exact_length_meters' => $exactLength,
            'certificate' => $certificate,
        ];
    }

    /**
     * Memvalidasi sertifikat rute drone secara independen. Melempar exception jika sertifikat rusak.
     *
     * @param  array{path: list<array{x: int, y: int}>, length_meters: int, certificate: array<string, mixed>}  $routeResult
     * @param  list<list<array{x: int, y: int}>>  $noFlyZones
     */
    public function verifyCertificate(array $routeResult, int $maxBatteryRangeMeters, array $noFlyZones): bool
    {
        $path = $routeResult['path'] ?? [];
        if (count($path) < 2) {
            throw new InvalidArgumentException('Sertifikat rute rusak: path minimal terdiri dari 2 simpul');
        }

        // 1. Verifikasi tiap segmen tidak memotong interior no-fly zone
        $totalExact = 0.0;
        $count = count($path);
        for ($i = 0; $i < $count - 1; $i++) {
            $p1 = $path[$i];
            $p2 = $path[$i + 1];

            foreach ($noFlyZones as $zone) {
                if ($this->intersection->intersectsInterior($zone, $p1, $p2)) {
                    throw new InvalidArgumentException("Sertifikat rute rusak: segmen [({$p1['x']}, {$p1['y']}) -> ({$p2['x']}, {$p2['y']})] memotong interior no-fly zone");
                }
            }

            $dx = $p2['x'] - $p1['x'];
            $dy = $p2['y'] - $p1['y'];
            $totalExact += sqrt($dx * $dx + $dy * $dy);
        }

        // 2. Verifikasi total panjang sesuai pembulatan floor
        $computedFloor = (int) floor($totalExact);
        if ($computedFloor !== $routeResult['length_meters']) {
            throw new InvalidArgumentException("Sertifikat rute rusak: panjang rute tercatat ({$routeResult['length_meters']}m) tidak cocok dengan kalkulasi independen ({$computedFloor}m)");
        }

        // 3. Verifikasi dalam jangkauan baterai
        if ($computedFloor > $maxBatteryRangeMeters) {
            throw new InvalidArgumentException("Sertifikat rute rusak: jarak ({$computedFloor}m) melebihi jangkauan baterai ({$maxBatteryRangeMeters}m)");
        }

        return true;
    }

    /**
     * Membandingkan dua path simpul demi simpul secara leksikografis (x, lalu y).
     *
     * @param  list<array{x: int, y: int}>  $p1
     * @param  list<array{x: int, y: int}>  $p2
     */
    private function comparePaths(array $p1, array $p2): int
    {
        $len1 = count($p1);
        $len2 = count($p2);
        $minLen = min($len1, $len2);

        for ($i = 0; $i < $minLen; $i++) {
            $n1 = $p1[$i];
            $n2 = $p2[$i];

            if ($n1['x'] !== $n2['x']) {
                return $n1['x'] <=> $n2['x'];
            }
            if ($n1['y'] !== $n2['y']) {
                return $n1['y'] <=> $n2['y'];
            }
        }

        return $len1 <=> $len2;
    }

    /**
     * @param  array{x: int, y: int}  $point
     */
    private function nodeKey(array $point): string
    {
        return "{$point['x']},{$point['y']}";
    }
}
