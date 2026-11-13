<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use DomainException;
use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\PutawaySuggestionCertificate;
use Modules\Wms\Domain\Certificates\PutawaySuggestionCertificateVerifier;

/**
 * H1 — PutawaySlotter: algoritma penempatan barang ke bin berbasis kelas ABC & kapasitas (integer murni).
 */
final class PutawaySlotter
{
    public function __construct(
        private readonly PutawaySuggestionCertificateVerifier $verifier = new PutawaySuggestionCertificateVerifier,
    ) {}

    /**
     * Merencanakan alokasi putaway ke bin.
     *
     * @param  list<array{id: string|int, distance: int, freeCapacity?: int, free_capacity?: int}>  $bins
     * @return array{
     *     mode: string,
     *     allocations: list<array{bin_id: string, qty: int}>,
     *     certificate: PutawaySuggestionCertificate
     * }
     */
    public function plan(int $q, string $abcClass, array $bins): array
    {
        if ($q <= 0) {
            throw new DomainException('INVALID_QUANTITY');
        }

        $class = strtoupper(trim($abcClass));
        if (! in_array($class, ['A', 'B', 'C'], true)) {
            throw new InvalidArgumentException("Kelas ABC tidak valid: '{$abcClass}'.");
        }

        $normalized = $this->normalizeBins($bins);
        if ($normalized === []) {
            throw new DomainException('NO_CAPACITY');
        }

        $singleCandidates = array_values(array_filter(
            $normalized,
            static fn (array $bin): bool => $bin['freeCapacity'] >= $q
        ));

        if ($singleCandidates !== []) {
            usort($singleCandidates, fn (array $a, array $b): int => $this->compareSingle($a, $b, $q, $class));
            $best = $singleCandidates[0];
            $allocations = [['bin_id' => $best['id'], 'qty' => $q]];
            $mode = 'single';
        } else {
            $totalFree = 0;
            foreach ($normalized as $bin) {
                $totalFree += $bin['freeCapacity'];
            }
            if ($totalFree < $q) {
                throw new DomainException('NO_CAPACITY');
            }

            $ordered = $normalized;
            usort($ordered, fn (array $a, array $b): int => $this->compareSplit($a, $b, $class));

            $remaining = $q;
            $allocations = [];
            foreach ($ordered as $bin) {
                if ($remaining <= 0) {
                    break;
                }
                if ($bin['freeCapacity'] <= 0) {
                    continue;
                }
                $take = min($remaining, $bin['freeCapacity']);
                $allocations[] = ['bin_id' => $bin['id'], 'qty' => $take];
                $remaining -= $take;
            }
            $mode = 'split';
        }

        $certificate = PutawaySuggestionCertificate::create(
            requestedQty: $q,
            abcClass: $class,
            mode: $mode,
            allocations: $allocations,
            inputBins: $normalized,
        );
        $this->verifier->verify($certificate);

        return [
            'mode' => $mode,
            'allocations' => $allocations,
            'certificate' => $certificate,
        ];
    }

    /**
     * @param  list<array{id: string|int, distance: int, freeCapacity?: int, free_capacity?: int}>  $bins
     * @return list<array{id: string, distance: int, freeCapacity: int}>
     */
    private function normalizeBins(array $bins): array
    {
        $out = [];
        foreach ($bins as $bin) {
            $free = (int) ($bin['freeCapacity'] ?? $bin['free_capacity'] ?? 0);
            if ($free <= 0) {
                continue;
            }
            $out[] = [
                'id' => (string) $bin['id'],
                'distance' => (int) $bin['distance'],
                'freeCapacity' => $free,
            ];
        }

        return $out;
    }

    /**
     * @param  array{id: string, distance: int, freeCapacity: int}  $a
     * @param  array{id: string, distance: int, freeCapacity: int}  $b
     */
    private function compareSingle(array $a, array $b, int $q, string $class): int
    {
        $wasteA = $a['freeCapacity'] - $q;
        $wasteB = $b['freeCapacity'] - $q;

        return match ($class) {
            'A' => [$a['distance'], $wasteA, $a['id']] <=> [$b['distance'], $wasteB, $b['id']],
            'B' => [$wasteA, $a['distance'], $a['id']] <=> [$wasteB, $b['distance'], $b['id']],
            'C' => [-$a['distance'], $wasteA, $a['id']] <=> [-$b['distance'], $wasteB, $b['id']],
        };
    }

    /**
     * @param  array{id: string, distance: int, freeCapacity: int}  $a
     * @param  array{id: string, distance: int, freeCapacity: int}  $b
     */
    private function compareSplit(array $a, array $b, string $class): int
    {
        return match ($class) {
            'A' => [$a['distance'], -$a['freeCapacity'], $a['id']] <=> [$b['distance'], -$b['freeCapacity'], $b['id']],
            'B' => [$a['freeCapacity'], $a['distance'], $a['id']] <=> [$b['freeCapacity'], $b['distance'], $b['id']],
            'C' => [-$a['distance'], $a['freeCapacity'], $a['id']] <=> [-$b['distance'], $b['freeCapacity'], $b['id']],
        };
    }
}
