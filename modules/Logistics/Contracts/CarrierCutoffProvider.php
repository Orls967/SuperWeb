<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

/**
 * Kontrak aditif Logistics untuk jadwal cut-off kurir per hub/gudang.
 */
interface CarrierCutoffProvider
{
    /**
     * Daftar jadwal cut-off kurir pada hari berjalan untuk suatu kode gudang/hub.
     *
     * @return list<array{
     *     carrier_code: string,
     *     carrier_name: string,
     *     cutoff_time: string,
     *     minutes_remaining: int,
     *     is_closed: bool
     * }>
     */
    public function getCutoffsForWarehouse(string $warehouseCode): array;
}
