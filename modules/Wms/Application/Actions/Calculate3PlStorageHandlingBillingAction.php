<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Wms\Domain\Models\Warehouse;

final class Calculate3PlStorageHandlingBillingAction extends BaseAction
{
    /**
     * Calculates 3PL storage, handling, and value-added service billing in IDR integer.
     *
     * @param  array{
     *     pallet_count: int,
     *     storage_days: int,
     *     storage_rate_per_day_idr: int,
     *     inbound_units: int,
     *     handling_in_rate_idr: int,
     *     outbound_units: int,
     *     handling_out_rate_idr: int,
     *     vas_fee_idr?: int
     * } $billingParams
     * @return array{
     *     storage_cost_idr: int,
     *     handling_in_cost_idr: int,
     *     handling_out_cost_idr: int,
     *     vas_cost_idr: int,
     *     subtotal_idr: int,
     *     tax_idr: int,
     *     total_billing_idr: int
     * }
     */
    public function execute(Warehouse $warehouse, int $clientId, array $billingParams): array
    {
        $palletCount = $billingParams['pallet_count'] ?? 0;
        $storageDays = $billingParams['storage_days'] ?? 0;
        $storageRate = $billingParams['storage_rate_per_day_idr'] ?? 0;
        $inboundUnits = $billingParams['inbound_units'] ?? 0;
        $handlingInRate = $billingParams['handling_in_rate_idr'] ?? 0;
        $outboundUnits = $billingParams['outbound_units'] ?? 0;
        $handlingOutRate = $billingParams['handling_out_rate_idr'] ?? 0;
        $vasFee = $billingParams['vas_fee_idr'] ?? 0;

        if ($palletCount < 0 || $storageDays < 0 || $storageRate < 0) {
            throw new InvalidArgumentException('Parameter perhitungan storage 3PL tidak valid.');
        }

        $storageCost = $palletCount * $storageDays * $storageRate;
        $handlingInCost = $inboundUnits * $handlingInRate;
        $handlingOutCost = $outboundUnits * $handlingOutRate;
        $vasCost = max(0, $vasFee);

        $subtotal = $storageCost + $handlingInCost + $handlingOutCost + $vasCost;
        // PPN 11% in integer
        $tax = (int) intdiv($subtotal * 11, 100);
        $totalBilling = $subtotal + $tax;

        $this->audit('wms.3pl_billing_calculated', $warehouse, null, [
            'client_id' => $clientId,
            'subtotal_idr' => $subtotal,
            'total_billing_idr' => $totalBilling,
        ]);

        return [
            'storage_cost_idr' => $storageCost,
            'handling_in_cost_idr' => $handlingInCost,
            'handling_out_cost_idr' => $handlingOutCost,
            'vas_cost_idr' => $vasCost,
            'subtotal_idr' => $subtotal,
            'tax_idr' => $tax,
            'total_billing_idr' => $totalBilling,
        ];
    }
}
