<?php

namespace Modules\Logistics\Application\Services\Reverse;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Logistics\Domain\Models\Reverse\ReverseItem;
use Modules\Logistics\Domain\Models\Reverse\ReverseOrder;

class ReverseLogisticsService
{
    // Avoided CO2 emission factor per kg recycled material (average 2.1 kg CO2e / kg)
    public const AVOIDED_CO2_PER_KG = 2.1;

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 79.2 Trigger reverse shipment from domain event idempotently
     */
    public function triggerReverseOrderFromEvent(
        string $eventTriggerType,
        string $sourceReferenceId,
        string $pickupLocation,
        string $destinationFacility,
        array $itemsData
    ): ReverseOrder {
        return DB::transaction(function () use ($eventTriggerType, $sourceReferenceId, $pickupLocation, $destinationFacility, $itemsData) {
            $existing = ReverseOrder::where('event_trigger_type', $eventTriggerType)
                ->where('source_reference_id', $sourceReferenceId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing; // Idempotent check
            }

            $orderCode = 'REV-'.strtoupper(bin2hex(random_bytes(6)));
            $manifestNo = 'MNF-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
            $custodyHash = hash('sha256', "{$orderCode}:{$manifestNo}:{$sourceReferenceId}:".json_encode($itemsData));

            $order = ReverseOrder::create([
                'reverse_code' => $orderCode,
                'event_trigger_type' => $eventTriggerType,
                'source_reference_id' => $sourceReferenceId,
                'pickup_location' => $pickupLocation,
                'destination_facility' => $destinationFacility,
                'status' => 'SCHEDULED',
                'manifest_number' => $manifestNo,
                'custody_hash' => $custodyHash,
                'total_appraised_value_idr' => 0,
                'avoided_emissions_kg_co2' => 0,
            ]);

            $totalValue = 0;
            $totalKg = 0;

            foreach ($itemsData as $item) {
                $qty = (float) $item['quantity'];
                $unitPrice = (int) ($item['appraised_unit_price_idr'] ?? 0);
                $subtotal = (int) round($qty * $unitPrice);

                ReverseItem::create([
                    'reverse_order_id' => $order->id,
                    'item_category' => $item['category'],
                    'description' => $item['description'],
                    'quantity_kg_or_units' => $qty,
                    'unit_of_measure' => $item['uom'] ?? 'KG',
                    'recycling_target' => $item['recycling_target'],
                    'appraised_unit_price_idr' => $unitPrice,
                    'subtotal_idr' => $subtotal,
                ]);

                $totalValue += $subtotal;
                if (($item['uom'] ?? 'KG') === 'KG' || ($item['uom'] ?? 'KG') === 'LITER') {
                    $totalKg += $qty;
                }
            }

            $avoidedEmissions = round($totalKg * self::AVOIDED_CO2_PER_KG, 2);

            $order->update([
                'total_appraised_value_idr' => $totalValue,
                'avoided_emissions_kg_co2' => $avoidedEmissions,
            ]);

            return $order;
        });
    }

    /**
     * 79.3 Ingest circular raw material into Manufacturing/Recycling and post to Ledger
     */
    public function processCircularIntake(ReverseOrder $order): ReverseOrder
    {
        return DB::transaction(function () use ($order) {
            $locked = ReverseOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'PROCESSED') {
                return $locked;
            }

            $locked->update(['status' => 'PROCESSED']);
            $val = $locked->total_appraised_value_idr;

            if ($val > 0) {
                // Post circular intake to ledger:
                // Debit MFG Scrap/Recycled Raw Material Inventory
                // Credit Logistics Circular Logistics Revenue
                $this->ledgerService->post(new PostingDTO(
                    type: 'CIRCULAR_ECONOMY_INTAKE',
                    description: "Circular intake from reverse order {$locked->reverse_code}",
                    idempotencyKey: "CIR-INT-{$locked->reverse_code}",
                    entries: [
                        PostingEntryDTO::forCode('mfg:scrap_inbound:IDR', 'IDR', $val),
                        PostingEntryDTO::forCode('lgx:circular_revenue:IDR', 'IDR', -$val),
                    ],
                    referenceType: 'REVERSE_LOGISTICS',
                    referenceId: (string) $locked->id,
                ));
            }

            return $locked;
        });
    }
}
