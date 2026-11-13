<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use DomainException;
use InvalidArgumentException;

/**
 * Alokasi stok FEFO (First-Expired, First-Out) deterministik lintas bin & lot.
 */
final class FefoLotAllocator
{
    /**
     * @param  list<array{bin_id: string, lot_number: string, expiry_epoch: int, available_qty: int, status: string}>  $lots
     * @return list<array{bin_id: string, lot_number: string, qty: int}>
     */
    public function allocate(int $requestedQty, array $lots, int $minRemainingShelfLifeEpoch = 0): array
    {
        if ($requestedQty <= 0) {
            throw new InvalidArgumentException('Kuantitas alokasi FEFO harus > 0.');
        }

        $eligible = array_values(array_filter(
            $lots,
            static fn (array $lot): bool => $lot['status'] === 'available'
                && $lot['available_qty'] > 0
                && ($lot['expiry_epoch'] === 0 || $lot['expiry_epoch'] >= $minRemainingShelfLifeEpoch)
        ));

        usort($eligible, static function (array $a, array $b): int {
            $expA = $a['expiry_epoch'] === 0 ? PHP_INT_MAX : $a['expiry_epoch'];
            $expB = $b['expiry_epoch'] === 0 ? PHP_INT_MAX : $b['expiry_epoch'];

            return [$expA, $a['bin_id'], $a['lot_number']] <=> [$expB, $b['bin_id'], $b['lot_number']];
        });

        $remaining = $requestedQty;
        $allocations = [];

        foreach ($eligible as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $lot['available_qty']);
            $allocations[] = [
                'bin_id' => (string) $lot['bin_id'],
                'lot_number' => (string) $lot['lot_number'],
                'qty' => $take,
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new DomainException("Stok FEFO layak tidak mencukupi: kurang {$remaining} unit.");
        }

        return $allocations;
    }
}
