<?php

declare(strict_types=1);

namespace Modules\Intercompany\Contracts;

interface SubledgerTotalProvider
{
    /**
     * Mendapatkan total saldo piutang intercompany unsettled untuk entitas kreditur.
     */
    public function getTotalReceivableMinor(string $entityCode, string $currency = 'IDR'): int;

    /**
     * Mendapatkan total saldo hutang intercompany unsettled untuk entitas debitur.
     */
    public function getTotalPayableMinor(string $entityCode, string $currency = 'IDR'): int;

    /**
     * Mendapatkan rekapitulasi saldo timbal balik bilateral antar dua entitas.
     *
     * @return array{
     *     seller_entity: string,
     *     buyer_entity: string,
     *     receivable_minor: int,
     *     payable_minor: int,
     *     net_discrepancy_minor: int,
     *     status: string
     * }
     */
    public function getBilateralReciprocalSummary(string $sellerEntity, string $buyerEntity, string $currency = 'IDR'): array;

    /**
     * Mendapatkan total laba persediaan intra-grup yang belum terealisasi pada akhir periode.
     */
    public function getUnrealizedInventoryProfitTotalIdr(string $period): int;
}
