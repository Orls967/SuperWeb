<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\TableBottleOrder;

final class TableConsumptionAuditService
{
    /**
     * Audit table consumption orders, verifying authentic RFID bottle seals and tab balances.
     *
     * @return array{
     *     booking_code: string,
     *     minimum_spend_idr: int,
     *     deposit_held_idr: int,
     *     total_bottles_ordered: int,
     *     valid_rfid_seals: int,
     *     consumed_amount_idr: int,
     *     shortfall_idr: int,
     *     final_charge_idr: int,
     *     unauthorized_bottles_detected: bool
     * }
     */
    public function auditTableConsumption(EntertainmentTableBooking $booking): array
    {
        $orders = TableBottleOrder::where('booking_id', $booking->id)->get();

        $totalBottles = $orders->count();
        $validSeals = 0;
        $consumedAmount = 0;
        $unauthorized = false;

        foreach ($orders as $order) {
            $consumedAmount += (int) $order->total_price_idr;

            if ($order->rfid_seal_code !== null && str_starts_with($order->rfid_seal_code, 'SEAL-')) {
                $validSeals++;
            } else {
                $unauthorized = true;
            }
        }

        $minSpend = (int) $booking->minimum_spend_idr;
        $deposit = (int) $booking->deposit_amount_idr;

        $shortfall = max(0, $minSpend - $consumedAmount);
        // Billing rule: pelanggan membayar max(minimum spend, konsumsi aktual) dikurangi deposit
        $finalCharge = max(0, max($minSpend, $consumedAmount) - $deposit);

        return [
            'booking_code' => $booking->booking_code,
            'minimum_spend_idr' => $minSpend,
            'deposit_held_idr' => $deposit,
            'total_bottles_ordered' => $totalBottles,
            'valid_rfid_seals' => $validSeals,
            'consumed_amount_idr' => $consumedAmount,
            'shortfall_idr' => $shortfall,
            'final_charge_idr' => $finalCharge,
            'unauthorized_bottles_detected' => $unauthorized,
        ];
    }

    /**
     * Record RFID verified bottle dispatch to table.
     */
    public function recordBottleDispatch(
        EntertainmentTableBooking $booking,
        string $bottleSku,
        string $bottleName,
        int $unitPriceIdr,
        string $rfidSealCode
    ): TableBottleOrder {
        return TableBottleOrder::create([
            'booking_id' => $booking->id,
            'bottle_sku' => $bottleSku,
            'bottle_name' => $bottleName,
            'quantity' => 1,
            'unit_price_idr' => $unitPriceIdr,
            'total_price_idr' => $unitPriceIdr,
            'rfid_seal_code' => $rfidSealCode,
            'is_opened' => true,
            'opened_at' => CarbonImmutable::now(),
        ]);
    }
}
