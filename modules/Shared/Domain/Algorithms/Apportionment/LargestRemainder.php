<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Apportionment;

use Brick\Math\BigRational;
use InvalidArgumentException;

/**
 * Algoritma pembagian proporsional sisa terbesar (Largest Remainder / Hamilton-Hare method).
 * Menjamin jumlah alokasi persis sama dengan total (Σ hasil = total) tanpa galat pembulatan.
 *
 * Kompleksitas: O(N log N) di mana N adalah jumlah elemen (sortir sisa pecahan).
 */
final class LargestRemainder
{
    /**
     * @param  int  $total  Jumlah total integer yang akan dialokasikan (harus >= 0)
     * @param  list<array{key: string, weight: BigRational|int}>  $items  Daftar item dan bobot
     * @return array<string, int> Peta key => jumlah alokasi integer
     */
    public function apportion(int $total, array $items): array
    {
        if ($total < 0) {
            throw new InvalidArgumentException("Total tidak boleh negatif: {$total}");
        }

        if ($items === []) {
            if ($total === 0) {
                return [];
            }
            throw new InvalidArgumentException('Daftar item tidak boleh kosong bila total > 0');
        }

        // Hitung total bobot
        $totalWeight = BigRational::zero();
        $normalizedItems = [];

        foreach ($items as $item) {
            if (! isset($item['key'], $item['weight'])) {
                throw new InvalidArgumentException('Setiap item wajib memiliki field key dan weight');
            }

            $weight = $item['weight'] instanceof BigRational
                ? $item['weight']
                : BigRational::of($item['weight']);

            if ($weight->isNegative()) {
                throw new InvalidArgumentException("Bobot item '{$item['key']}' tidak boleh negatif: {$weight}");
            }

            $totalWeight = $totalWeight->plus($weight);
            $normalizedItems[] = [
                'key' => (string) $item['key'],
                'weight' => $weight,
            ];
        }

        if ($totalWeight->isZero()) {
            throw new InvalidArgumentException('Total bobot tidak boleh 0');
        }

        if ($total === 0) {
            $zeroResult = [];
            foreach ($normalizedItems as $item) {
                $zeroResult[$item['key']] = 0;
            }

            return $zeroResult;
        }

        $totalRational = BigRational::of($total);
        $allocations = [];
        $remainders = [];
        $sumFloors = 0;

        foreach ($normalizedItems as $idx => $item) {
            $key = $item['key'];
            // Kuota eksak: total * (weight / totalWeight)
            $exactQuota = $totalRational->multipliedBy($item['weight'])->dividedBy($totalWeight);

            $floor = $exactQuota->getNumerator()->quotient($exactQuota->getDenominator())->toInt();
            $remainder = $exactQuota->minus(BigRational::of($floor));

            $allocations[$key] = $floor;
            $sumFloors += $floor;

            $remainders[] = [
                'idx' => $idx,
                'key' => $key,
                'remainder' => $remainder,
            ];
        }

        $deficit = $total - $sumFloors;

        // Sort remainder descending, tie-break key ascending
        usort($remainders, function (array $a, array $b): int {
            /** @var BigRational $remA */
            $remA = $a['remainder'];
            /** @var BigRational $remB */
            $remB = $b['remainder'];

            $cmp = $remB->compareTo($remA);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['key'], (string) $b['key']);
        });

        for ($i = 0; $i < $deficit; $i++) {
            $key = $remainders[$i]['key'];
            $allocations[$key]++;
        }

        return $allocations;
    }
}
