<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

use App\Models\User;

/**
 * Contract for cross-module shipment booking.
 *
 * Modules like Store use this to create shipments without
 * importing Logistics Domain classes directly.
 */
interface ShipmentBooking
{
    /**
     * Create a shipment for a store order.
     *
     * @param  array{
     *     origin_code: string,
     *     destination_address: array{street: string, city: string, postal_code?: string, province?: string},
     *     consignee_name: string,
     *     consignee_phone: string,
     *     packages: array<int, array{weight_g: int, length_mm: int, width_mm: int, height_mm: int, description: string}>,
     *     declared_value_idr: int,
     *     source_type: string,
     *     source_id: string|int,   // int utk store order, string utk UUID (mis. PO)
     *     amount_idr: int,
     * }  $data
     * @return array{tracking_number: string, shipment_id: int}
     */
    public function bookForOrder(User $shipper, array $data): array;

    /**
     * Cancel a shipment created for an external source (e.g. store order),
     * reversing unearned freight liability.
     */
    public function cancelForOrder(string $sourceType, string|int $sourceId, ?string $reason = null): bool;
}
