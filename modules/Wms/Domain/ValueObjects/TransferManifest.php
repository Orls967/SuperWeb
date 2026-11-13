<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Manifest transfer antar-gudang dengan daftar item & total nilai integer IDR.
 */
final readonly class TransferManifest
{
    /**
     * @param  list<array{product_id: int, qty_base: int, unit_cost_idr: int}>  $lines
     */
    public function __construct(
        public string $transferNumber,
        public string $originWarehouseCode,
        public string $destinationWarehouseCode,
        public array $lines,
    ) {
        if ($transferNumber === '' || $originWarehouseCode === '' || $destinationWarehouseCode === '') {
            throw new InvalidArgumentException('Nomor transfer dan kode gudang asal/tujuan wajib diisi.');
        }
        if ($originWarehouseCode === $destinationWarehouseCode) {
            throw new InvalidArgumentException('Gudang asal dan tujuan tidak boleh sama.');
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Manifest transfer harus memiliki minimal 1 baris item.');
        }
    }

    public function totalQtyBase(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += (int) $line['qty_base'];
        }

        return $total;
    }

    public function totalValueIdr(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += ((int) $line['qty_base']) * ((int) $line['unit_cost_idr']);
        }

        return $total;
    }
}
