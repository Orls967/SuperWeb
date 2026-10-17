<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Facades\Log;
use Modules\Core\Domain\Models\SimEventSpine;

class CrossModuleSpineHandlers
{
    /**
     * Register real cross-module consumer handlers according to EventCatalog.
     */
    public static function registerAll(): void
    {
        // 1. Logistics consuming store.order.paid.v1
        SpineConsumerRegistry::register('logistics_worker', 'store.order.paid.v1', function (SimEventSpine $event): void {
            $orderId = $event->payload['order_id'] ?? null;
            $customerId = $event->payload['customer_id'] ?? null;
            if ($orderId) {
                Log::info("CrossModuleSpine: Logistics preparing shipment dispatch for Order {$orderId} (Customer {$customerId})");
            }
        });

        // 2. Pricing consuming store.order.paid.v1
        SpineConsumerRegistry::register('pricing_worker', 'store.order.paid.v1', function (SimEventSpine $event): void {
            $orderId = $event->payload['order_id'] ?? null;
            if ($orderId) {
                Log::info("CrossModuleSpine: Pricing resolving price lock for Order {$orderId}");
            }
        });

        // 3. Insurance consuming lgx.shipment.delivered.v1
        SpineConsumerRegistry::register('insurance_worker', 'lgx.shipment.delivered.v1', function (SimEventSpine $event): void {
            $shipmentId = $event->payload['shipment_id'] ?? null;
            if ($shipmentId) {
                Log::info("CrossModuleSpine: Insurance closing transit cargo policy for Shipment {$shipmentId}");
            }
        });

        // 4. Esg consuming auto.ev.session_completed.v1
        SpineConsumerRegistry::register('esg_worker', 'auto.ev.session_completed.v1', function (SimEventSpine $event): void {
            $kwh = (float) ($event->payload['kwh_consumed'] ?? 0);
            $vin = $event->payload['vin'] ?? 'UNKNOWN';
            Log::info("CrossModuleSpine: ESG calculating carbon offset for EV VIN {$vin}: {$kwh} kWh consumed");
        });

        // 5. Payment consuming hsp.episode.discharged.v1
        SpineConsumerRegistry::register('payment_worker', 'hsp.episode.discharged.v1', function (SimEventSpine $event): void {
            $episodeId = $event->payload['episode_id'] ?? null;
            $tariff = (int) ($event->payload['total_tariff_idr'] ?? 0);
            if ($episodeId) {
                Log::info("CrossModuleSpine: Payment processing hospital discharge settlement for Episode {$episodeId}: Rp {$tariff}");
            }
        });

        // 6. Hotel consuming htl.night.audited.v1
        SpineConsumerRegistry::register('hotel_worker', 'htl.night.audited.v1', function (SimEventSpine $event): void {
            $property = $event->payload['property_code'] ?? null;
            $revenue = (int) ($event->payload['room_revenue_idr'] ?? 0);
            Log::info("CrossModuleSpine: Hotel acknowledging night audit revenue for Property {$property}: Rp {$revenue}");
        });

        // 7. Venue consuming ven.event.closed.v1
        SpineConsumerRegistry::register('venue_worker', 'ven.event.closed.v1', function (SimEventSpine $event): void {
            $eventId = $event->payload['event_id'] ?? null;
            $revenue = (int) ($event->payload['ticket_revenue_idr'] ?? 0);
            Log::info("CrossModuleSpine: Venue final settlement for Event {$eventId}: Rp {$revenue}");
        });

        // 8. Logistics consuming min.weighbridge.recorded.v1
        SpineConsumerRegistry::register('logistics_worker', 'min.weighbridge.recorded.v1', function (SimEventSpine $event): void {
            $ticketId = $event->payload['ticket_id'] ?? null;
            $weightTon = (float) ($event->payload['net_weight_ton'] ?? 0);
            Log::info("CrossModuleSpine: Logistics custody logging for Mineral Ticket {$ticketId}: {$weightTon} tons");
        });

        // 9. Wealth consuming hcm.payroll.posted.v1
        SpineConsumerRegistry::register('wealth_worker', 'hcm.payroll.posted.v1', function (SimEventSpine $event): void {
            $payrollId = $event->payload['payroll_run_id'] ?? null;
            $netIdr = (int) ($event->payload['total_net_idr'] ?? 0);
            Log::info("CrossModuleSpine: Wealth allocation evaluating payroll auto-invest for Run {$payrollId}: Rp {$netIdr}");
        });
    }
}
