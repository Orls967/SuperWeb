<?php

declare(strict_types=1);

namespace Modules\Wms\Contracts;

/**
 * Kontrak pembaca nilai persediaan gudang dan rekonsiliasi terhadap buku besar.
 */
interface WarehouseValuationReader
{
    /**
     * Mengembalikan nilai subledger vs saldo buku besar untuk satu gudang (dalam IDR minor units).
     *
     * @return array{
     *     warehouse_id: string|int,
     *     warehouse_code: string,
     *     account_code: string,
     *     subledger_value_idr: int,
     *     ledger_balance_idr: int,
     *     discrepancy_idr: int
     * }
     */
    public function getWarehouseValuationIdr(string|int $warehouseId): array;

    /**
     * Mengembalikan ringkasan nilai persediaan seluruh gudang aktif.
     *
     * @return list<array{
     *     warehouse_id: string|int,
     *     warehouse_code: string,
     *     account_code: string,
     *     subledger_value_idr: int,
     *     ledger_balance_idr: int,
     *     discrepancy_idr: int
     * }>
     */
    public function getAllWarehousesValuationIdr(): array;
}
