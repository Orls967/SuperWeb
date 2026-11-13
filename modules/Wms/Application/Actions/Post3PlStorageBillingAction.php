<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use InvalidArgumentException;

/**
 * Perhitungan & posting tagihan jasa penyimpanan serta handling 3PL (D41-12).
 */
final class Post3PlStorageBillingAction
{
    public function __construct(
        private readonly WmsLedgerPoster $poster,
    ) {}

    /**
     * @return array{
     *     billing_ref: string,
     *     pallet_days: int,
     *     handling_units: int,
     *     storage_fee_idr: int,
     *     handling_fee_idr: int,
     *     total_billing_idr: int
     * }
     */
    public function execute(
        string $billingRef,
        int $palletDays,
        int $storageRateIdrPerPalletDay,
        int $handlingUnits,
        int $handlingRateIdrPerUnit,
    ): array {
        if ($palletDays < 0 || $handlingUnits < 0 || $storageRateIdrPerPalletDay < 0 || $handlingRateIdrPerUnit < 0) {
            throw new InvalidArgumentException('Parameter tagihan 3PL tidak boleh negatif.');
        }

        $storageFee = $palletDays * $storageRateIdrPerPalletDay;
        $handlingFee = $handlingUnits * $handlingRateIdrPerUnit;
        $total = $storageFee + $handlingFee;

        if ($total > 0) {
            $this->poster->post3PlBilling($total, $billingRef);
        }

        return [
            'billing_ref' => $billingRef,
            'pallet_days' => $palletDays,
            'handling_units' => $handlingUnits,
            'storage_fee_idr' => $storageFee,
            'handling_fee_idr' => $handlingFee,
            'total_billing_idr' => $total,
        ];
    }
}
