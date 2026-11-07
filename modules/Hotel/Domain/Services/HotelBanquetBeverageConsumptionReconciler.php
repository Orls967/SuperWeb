<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelBanquetBeverageConsumptionReconciler
{
    /**
     * Merekonsiliasi konsumsi minuman keras / anggur terbuka per botol (Open Bar Consumption) pasca acara banquet.
     *
     * @param  list<array{item_code: string, opened_bottles: int, returned_sealed_bottles: int, bottle_rate_idr: int}>  $beverageLogs
     * @return array{contract_id: string, total_consumed_bottles: int, total_beverage_charge_idr: int, corkage_fee_idr: int, grand_total_beverage_idr: int}
     */
    public function reconcileBeverageBilling(string $contractId, array $beverageLogs, int $corkageBottles = 0, int $corkageRateIdr = 250000): array
    {
        $totalConsumed = 0;
        $totalCharge = 0;

        foreach ($beverageLogs as $log) {
            $opened = (int) ($log['opened_bottles'] ?? 0);
            $rate = (int) ($log['bottle_rate_idr'] ?? 0);

            $totalConsumed += $opened;
            $totalCharge += ($opened * $rate);
        }

        $corkageFee = $corkageBottles * $corkageRateIdr;
        $grandTotal = $totalCharge + $corkageFee;

        return [
            'contract_id' => $contractId,
            'total_consumed_bottles' => $totalConsumed,
            'total_beverage_charge_idr' => $totalCharge,
            'corkage_fee_idr' => $corkageFee,
            'grand_total_beverage_idr' => $grandTotal,
        ];
    }
}
