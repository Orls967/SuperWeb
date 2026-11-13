<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

/**
 * Kontrak aditif Logistics untuk memantau pengiriman yang masih berstatus in-transit.
 */
interface InTransitShipmentProvider
{
    /**
     * Daftar pengiriman in-transit menuju atau dari gudang tertentu.
     *
     * @return list<array{
     *     shipment_id: int,
     *     tracking_number: string,
     *     origin_code: string,
     *     destination_code: string,
     *     status: string,
     *     declared_value_idr: int,
     *     hours_in_transit: int
     * }>
     */
    public function getInTransitForWarehouse(string $warehouseCode): array;

    /**
     * Daftar pengiriman in-transit yang melewati ambang batas jam SLA.
     *
     * @return list<array{
     *     shipment_id: int,
     *     tracking_number: string,
     *     origin_code: string,
     *     destination_code: string,
     *     hours_in_transit: int
     * }>
     */
    public function getOverdueInTransitShipments(int $maxHours = 72): array;
}
