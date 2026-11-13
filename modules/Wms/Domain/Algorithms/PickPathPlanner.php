<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use DomainException;
use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\PickSequenceCertificate;
use Modules\Wms\Domain\Certificates\PickSequenceCertificateVerifier;

/**
 * H2 — PickPathPlanner: perbandingan lintasan S-Shape vs Largest-Gap (integer murni).
 */
final class PickPathPlanner
{
    public function __construct(
        private readonly PickSequenceCertificateVerifier $verifier = new PickSequenceCertificateVerifier,
    ) {}

    /**
     * @param  list<array{aisle: int, pos: int, bin_id?: string}>  $picks
     * @return array{
     *     strategy: string,
     *     total_distance: int,
     *     s_shape_distance: int,
     *     largest_gap_distance: int,
     *     sequence: list<array{aisle: int, pos: int, bin_id: string}>,
     *     certificate: PickSequenceCertificate
     * }
     */
    public function plan(int $aisleWidth, int $aisleLength, array $picks): array
    {
        if ($picks === []) {
            throw new DomainException('EMPTY_PICK_LIST');
        }
        if ($aisleWidth <= 0 || $aisleLength <= 0) {
            throw new InvalidArgumentException('Dimensi lorong harus lebih dari 0.');
        }

        $byAisle = [];
        foreach ($picks as $pick) {
            $aisle = (int) $pick['aisle'];
            $pos = (int) $pick['pos'];
            $binId = (string) ($pick['bin_id'] ?? "A{$aisle}-P{$pos}");
            $byAisle[$aisle][] = ['aisle' => $aisle, 'pos' => $pos, 'bin_id' => $binId];
        }
        ksort($byAisle);

        $aisles = array_keys($byAisle);
        $maxAisle = max($aisles);
        $crossAisleDist = 2 * max(0, $maxAisle - 1) * $aisleWidth;

        // 1. S-Shape distance
        $aisleCount = count($aisles);
        $sShapeVertical = 0;
        foreach ($aisles as $idx => $aisle) {
            $positions = array_column($byAisle[$aisle], 'pos');
            if ($idx === $aisleCount - 1 && $aisleCount % 2 === 1) {
                $sShapeVertical += 2 * max($positions);
            } else {
                $sShapeVertical += $aisleLength;
            }
        }
        $sShapeDistance = $crossAisleDist + $sShapeVertical;

        // 2. Largest-Gap distance
        $largestGapVertical = 0;
        if ($aisleCount === 1) {
            $positions = array_column($byAisle[$aisles[0]], 'pos');
            $largestGapVertical = 2 * max($positions);
        } else {
            foreach ($aisles as $idx => $aisle) {
                if ($idx === 0 || $idx === $aisleCount - 1) {
                    $largestGapVertical += $aisleLength;
                } else {
                    $positions = array_column($byAisle[$aisle], 'pos');
                    sort($positions);
                    $maxGap = $this->findLargestGap($positions, $aisleLength);
                    $largestGapVertical += 2 * ($aisleLength - $maxGap);
                }
            }
        }
        $largestGapDistance = $crossAisleDist + $largestGapVertical;

        // Tie-breaker: S_SHAPE
        $strategy = $sShapeDistance <= $largestGapDistance ? 'S_SHAPE' : 'LARGEST_GAP';
        $totalDistance = min($sShapeDistance, $largestGapDistance);

        $sequence = $strategy === 'S_SHAPE'
            ? $this->buildSShapeSequence($byAisle, $aisles)
            : $this->buildLargestGapSequence($byAisle, $aisles, $aisleLength);

        $certificate = PickSequenceCertificate::create(
            strategy: $strategy,
            totalDistance: $totalDistance,
            sShapeDistance: $sShapeDistance,
            largestGapDistance: $largestGapDistance,
            inputPickCount: count($picks),
            sequence: $sequence,
        );
        $this->verifier->verify($certificate);

        return [
            'strategy' => $strategy,
            'total_distance' => $totalDistance,
            's_shape_distance' => $sShapeDistance,
            'largest_gap_distance' => $largestGapDistance,
            'sequence' => $sequence,
            'certificate' => $certificate,
        ];
    }

    /**
     * @param  list<int>  $sortedPositions
     */
    private function findLargestGap(array $sortedPositions, int $aisleLength): int
    {
        $maxGap = $sortedPositions[0];
        $count = count($sortedPositions);
        for ($i = 0; $i < $count - 1; $i++) {
            $gap = $sortedPositions[$i + 1] - $sortedPositions[$i];
            if ($gap > $maxGap) {
                $maxGap = $gap;
            }
        }
        $tailGap = $aisleLength - $sortedPositions[$count - 1];

        return max($maxGap, $tailGap);
    }

    /**
     * @param  array<int, list<array{aisle: int, pos: int, bin_id: string}>>  $byAisle
     * @param  list<int>  $aisles
     * @return list<array{aisle: int, pos: int, bin_id: string}>
     */
    private function buildSShapeSequence(array $byAisle, array $aisles): array
    {
        $sequence = [];
        foreach ($aisles as $idx => $aisle) {
            $items = $byAisle[$aisle];
            usort($items, static function (array $a, array $b) use ($idx): int {
                $cmp = $idx % 2 === 0 ? $a['pos'] <=> $b['pos'] : $b['pos'] <=> $a['pos'];

                return $cmp !== 0 ? $cmp : ($a['bin_id'] <=> $b['bin_id']);
            });
            array_push($sequence, ...$items);
        }

        return $sequence;
    }

    /**
     * @param  array<int, list<array{aisle: int, pos: int, bin_id: string}>>  $byAisle
     * @param  list<int>  $aisles
     * @return list<array{aisle: int, pos: int, bin_id: string}>
     */
    private function buildLargestGapSequence(array $byAisle, array $aisles, int $aisleLength): array
    {
        $sequence = [];
        $count = count($aisles);

        foreach ($aisles as $idx => $aisle) {
            $items = $byAisle[$aisle];
            usort($items, static fn (array $a, array $b): int => [$a['pos'], $a['bin_id']] <=> [$b['pos'], $b['bin_id']]);
            if ($idx === $count - 1 && $count > 1) {
                $items = array_reverse($items);
            }
            array_push($sequence, ...$items);
        }

        return $sequence;
    }
}
