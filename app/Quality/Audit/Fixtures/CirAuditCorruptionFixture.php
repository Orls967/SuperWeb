<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Circular\Domain\Models\CirListing;
use Modules\Circular\Domain\Models\CirOrder;

final class CirAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'cir:audit';
    }

    public function corrupt(): string
    {
        $listing = CirListing::create([
            'listing_code' => 'CORRUPT-CIR-LST-999',
            'seller_party_id' => 1,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'available_grams' => 10_000_000,
            'unit_price_per_kg_idr' => 4_000,
            'mode' => 'fixed',
            'status' => 'active',
        ]);

        $order = CirOrder::create([
            'order_number' => 'CORRUPT-CIR-ORD-999',
            'listing_id' => $listing->id,
            'buyer_party_id' => 2,
            'seller_party_id' => 1,
            'material_code' => 'SCRAP_FE',
            'grade_code' => 'A',
            'ordered_grams' => 5_000_000,
            'accepted_grams' => 5_000_000,
            'unit_price_per_kg_idr' => 4_000,
            'escrow_amount_idr' => 20_000_000,
            'refund_amount_idr' => 0,
            'status' => 'settled',
            'reverse_shipment_id' => 999999,
            'plant_receipt_id' => 999999,
            'idempotency_key' => 'corrupt-cir-ord-key-999',
        ]);

        return $order->order_number;
    }
}
