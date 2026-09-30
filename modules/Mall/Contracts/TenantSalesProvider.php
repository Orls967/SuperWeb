<?php

declare(strict_types=1);

namespace Modules\Mall\Contracts;

use Modules\Mall\Domain\Models\Lease;

interface TenantSalesProvider
{
    /**
     * Menentukan apakah provider ini menangani penarikan data penjualan untuk kontrak lease ini.
     */
    public function supports(Lease $lease): bool;

    /**
     * Mengambil omzet penjualan bersih (net sales) dalam Rupiah untuk periode bulan bersangkutan ('YYYY-MM').
     */
    public function getMonthlySales(Lease $lease, string $periodMonth): int;

    /**
     * Mengambil jumlah transaksi untuk periode bulan bersangkutan.
     */
    public function getTransactionCount(Lease $lease, string $periodMonth): int;
}
