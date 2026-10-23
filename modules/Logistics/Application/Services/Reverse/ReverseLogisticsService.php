<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services\Reverse;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
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

            $seedKey = hash('sha256', "{$eventTriggerType}:{$sourceReferenceId}:{$pickupLocation}:{$destinationFacility}");
            $orderCode = 'REV-'.strtoupper(substr($seedKey, 0, 10));
            $manifestNo = 'MNF-'.strtoupper(substr(hash('sha256', "MNF:{$orderCode}:{$sourceReferenceId}"), 0, 12));
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
            $totalKg = 0.0;

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
                    entries: Posting::lines()
                        ->debit('mfg:scrap_inbound:IDR', $val)
                        ->credit('lgx:circular_revenue:IDR', $val)
                        ->toEntries('IDR'),
                    referenceType: 'REVERSE_LOGISTICS',
                    referenceId: (string) $locked->id,
                ));
            }

            return $locked;
        });
    }

    /**
     * Two-source audit for Reverse Logistics & Circular Economy:
     * Source 1: Verification of reverse orders, custody hash, and line-item totals.
     * Source 2: Verification of circular revenue ledger balance consistency.
     */
    public function auditCircular(): array
    {
        $discrepancies = [];
        $orders = ReverseOrder::with('items')->get();
        $ordersChecked = $orders->count();
        $itemsChecked = 0;

        foreach ($orders as $order) {
            $itemsChecked += $order->items->count();

            // 1. Verify item subtotal sum vs total_appraised_value_idr
            $calculatedTotal = (int) $order->items->sum('subtotal_idr');
            if ($calculatedTotal !== (int) $order->total_appraised_value_idr) {
                $discrepancies[] = "Order {$order->reverse_code} total mismatch: recorded {$order->total_appraised_value_idr}, calculated {$calculatedTotal}";
            }

            // 2. Verify avoided emissions calculation
            $totalKg = 0.0;
            foreach ($order->items as $item) {
                if (in_array($item->unit_of_measure, ['KG', 'LITER'], true)) {
                    $totalKg += (float) $item->quantity_kg_or_units;
                }
            }
            $expectedAvoided = round($totalKg * self::AVOIDED_CO2_PER_KG, 2);
            if (abs((float) $order->avoided_emissions_kg_co2 - $expectedAvoided) > 0.05) {
                $discrepancies[] = "Order {$order->reverse_code} emission mismatch: recorded {$order->avoided_emissions_kg_co2}, expected {$expectedAvoided}";
            }

            // 3. Verify custody hash presence
            if (empty($order->custody_hash) || strlen($order->custody_hash) !== 64) {
                $discrepancies[] = "Order {$order->reverse_code} has invalid custody hash";
            }
        }

        // Source 2: Ledger consistency for PROCESSED orders
        if (Schema::hasTable('bk_ledger_accounts')) {
            $processedOrders = ReverseOrder::where('status', 'PROCESSED')->get();
            $expectedLedgerRevenue = (int) $processedOrders->sum('total_appraised_value_idr');

            $rows = DB::select('SELECT cached_balance FROM bk_ledger_accounts WHERE code = ? LIMIT 1', ['lgx:circular_revenue:IDR']);
            if (! empty($rows)) {
                $actualLedgerRevenue = abs((int) $rows[0]->cached_balance);
                if ($expectedLedgerRevenue !== $actualLedgerRevenue) {
                    $discrepancies[] = "Ledger revenue mismatch: expected {$expectedLedgerRevenue}, got {$actualLedgerRevenue} in lgx:circular_revenue:IDR";
                }
            }
        }

        return [
            'orders_checked' => $ordersChecked,
            'items_checked' => $itemsChecked,
            'discrepancies' => $discrepancies,
            'passed' => empty($discrepancies),
        ];
    }
}
