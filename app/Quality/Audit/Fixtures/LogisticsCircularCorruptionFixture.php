<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Logistics\Domain\Models\Reverse\ReverseItem;
use Modules\Logistics\Domain\Models\Reverse\ReverseOrder;

final class LogisticsCircularCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'lgx:audit-circular';
    }

    public function corrupt(): string
    {
        $order = ReverseOrder::create([
            'reverse_code' => 'REV-CORRUPT-CIRCULAR-01',
            'event_trigger_type' => 'resto.waste_bulk',
            'source_reference_id' => 'CORRUPT-REF-999',
            'pickup_location' => 'Corrupt Kitchen',
            'destination_facility' => 'Corrupt Plant',
            'status' => 'SCHEDULED',
            'manifest_number' => 'MNF-CORRUPT-999',
            'custody_hash' => hash('sha256', 'corrupt'),
            'total_appraised_value_idr' => 999_999_999, // Injected corrupt total
            'avoided_emissions_kg_co2' => 210.0,
        ]);

        ReverseItem::create([
            'reverse_order_id' => $order->id,
            'item_category' => 'COOKING_OIL_JELANTAH',
            'description' => 'Used oil item',
            'quantity_kg_or_units' => 100.0,
            'unit_of_measure' => 'LITER',
            'recycling_target' => 'BIODIESEL_REFINERY',
            'appraised_unit_price_idr' => 8500,
            'subtotal_idr' => 850_000,
        ]);

        return 'REV-CORRUPT-CIRCULAR-01';
    }
}
