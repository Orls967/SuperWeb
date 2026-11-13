<?php

declare(strict_types=1);

namespace Modules\Distribution\Contracts;

/**
 * Komitmen pesanan terbuka dan fakta pemenuhan pesanan. Distribution adalah pemilik pesanan terbuka (dist_orders)
 * yang dipilih di L.0 W8-P6-fix; ControlTower memakai kontrak ini untuk ATP dan KPI OTIF / fill rate.
 */
interface OpenDemandProvider
{
    /**
     * Komitmen pesanan terbuka per periode horizon (indeks 0 = periode 1), panjang = $periods.
     *
     * @return list<int>
     */
    public function commitmentsByPeriod(string $itemCode, int $periods, string $asOfDate): array;

    /**
     * Baris pemenuhan pesanan pada rentang tanggal jatuh tempo (inklusif), terurut (due_date, order_ref).
     *
     * @return list<array{order_ref:string, customer_ref:string, item_code:string, ordered_qty:int, delivered_qty:int, due_date:string, delivered_date:?string}>
     */
    public function fulfillmentLines(string $fromDate, string $toDate): array;
}
