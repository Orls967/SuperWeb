<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

/**
 * Penyeimbang stok antar-gudang untuk mengurangi stockout & overstock secara deterministik.
 */
final class CrossWarehouseRebalancer
{
    /**
     * @param  list<array{warehouse_code: string, product_id: int, available_qty: int, min_qty: int, max_qty: int}>  $positions
     * @return list<array{product_id: int, from_warehouse: string, to_warehouse: string, qty: int}>
     */
    public function proposeTransfers(array $positions): array
    {
        $byProduct = [];
        foreach ($positions as $pos) {
            $byProduct[(int) $pos['product_id']][] = $pos;
        }
        ksort($byProduct);

        $transfers = [];

        foreach ($byProduct as $productId => $rows) {
            $surpluses = [];
            $deficits = [];

            foreach ($rows as $row) {
                $avail = (int) $row['available_qty'];
                $min = (int) $row['min_qty'];
                $max = (int) $row['max_qty'];

                if ($avail > $max) {
                    $surpluses[] = ['warehouse_code' => $row['warehouse_code'], 'qty' => $avail - $max];
                } elseif ($avail < $min) {
                    $deficits[] = ['warehouse_code' => $row['warehouse_code'], 'qty' => $min - $avail];
                }
            }

            foreach ($deficits as &$def) {
                foreach ($surpluses as &$sup) {
                    if ($def['qty'] <= 0 || $sup['qty'] <= 0) {
                        continue;
                    }
                    $move = min($def['qty'], $sup['qty']);
                    $transfers[] = [
                        'product_id' => $productId,
                        'from_warehouse' => $sup['warehouse_code'],
                        'to_warehouse' => $def['warehouse_code'],
                        'qty' => $move,
                    ];
                    $def['qty'] -= $move;
                    $sup['qty'] -= $move;
                }
            }
        }

        return $transfers;
    }
}
