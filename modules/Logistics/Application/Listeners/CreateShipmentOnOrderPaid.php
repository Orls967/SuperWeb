<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Store\Domain\Events\OrderPaid;
use Modules\Store\Domain\Models\Order;

/**
 * Listens to OrderPaid from the Store module and creates a shipment
 * for non-C2C, non-car-only orders that need physical delivery.
 */
class CreateShipmentOnOrderPaid
{
    public function __construct(
        private readonly ShipmentBooking $shipmentBooking,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = Order::with('items.product')->find($event->orderId);

        if (! $order) {
            return;
        }

        // Skip C2C orders (vehicle handover, no logistics)
        if ($order->isC2c()) {
            return;
        }

        // Skip car-only orders (immediate fulfillment, no shipping)
        $hasPhysicalItems = $order->items->contains(function ($item) {
            return $item->product && ! $item->product->is_car;
        });

        if (! $hasPhysicalItems) {
            return;
        }

        // Skip if shipment already exists for this order (idempotency)
        $existing = Shipment::where('source_type', 'store_order')
            ->where('source_id', $order->id)
            ->exists();

        if ($existing) {
            return;
        }

        $shipper = User::findOrFail($order->user_id);
        $address = $order->shipping_address ?? ['street' => 'Alamat Pembeli', 'city' => 'Banjarmasin'];

        // Build packages from order items (only physical items)
        $packages = [];
        foreach ($order->items as $item) {
            if ($item->product && ! $item->product->is_car) {
                for ($i = 0; $i < $item->qty; $i++) {
                    $packages[] = [
                        'weight_g' => 500, // default weight for store items
                        'length_mm' => 200,
                        'width_mm' => 150,
                        'height_mm' => 100,
                        'description' => $item->product->name ?? 'Sparepart/Aksesoris',
                    ];
                }
            }
        }

        if (empty($packages)) {
            return;
        }

        // Shipping cost is the shipping_fee from the order
        $shippingCost = max($order->shipping_fee, 0);

        try {
            $result = $this->shipmentBooking->bookForOrder($shipper, [
                'origin_code' => 'BJM-HUB', // Banjarmasin main hub
                'destination_address' => $address,
                'consignee_name' => $shipper->name,
                'consignee_phone' => '0812-0000-0000',
                'packages' => $packages,
                'declared_value_idr' => $order->subtotal,
                'source_type' => 'store_order',
                'source_id' => $order->id,
                'amount_idr' => $shippingCost > 0 ? $shippingCost : 15000, // minimum flat rate
            ]);

            // Update the order with the tracking number
            $order->update([
                'tracking_number' => $result['tracking_number'],
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to auto-create shipment for order #{$order->id}: {$e->getMessage()}");
        }
    }
}
