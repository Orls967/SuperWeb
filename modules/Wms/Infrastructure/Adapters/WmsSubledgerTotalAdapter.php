<?php

declare(strict_types=1);

namespace Modules\Wms\Infrastructure\Adapters;

use Modules\Intercompany\Contracts\SubledgerTotalProvider;
use Modules\Wms\Contracts\WarehouseValuationReader;

/**
 * Adaptor WMS ke SubledgerTotalProvider untuk period closing EnterpriseFinance.
 */
final class WmsSubledgerTotalAdapter implements SubledgerTotalProvider
{
    public function __construct(
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    public function getModuleName(): string
    {
        return 'Wms';
    }

    public function getTotalReceivableMinor(string $entityCode, string $currency = 'IDR'): int
    {
        return 0;
    }

    public function getTotalPayableMinor(string $entityCode, string $currency = 'IDR'): int
    {
        return 0;
    }

    public function getBilateralReciprocalSummary(string $sellerEntity, string $buyerEntity, string $currency = 'IDR'): array
    {
        return [
            'seller_entity' => $sellerEntity,
            'buyer_entity' => $buyerEntity,
            'receivable_minor' => 0,
            'payable_minor' => 0,
            'net_discrepancy_minor' => 0,
            'status' => 'BALANCED',
        ];
    }

    public function getUnrealizedInventoryProfitTotalIdr(string $period): int
    {
        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubledgerTotal(string $entityCode, string $asOfDate): array
    {
        $warehouses = $this->valuationReader->getAllWarehousesValuationIdr();
        $totalSubledger = 0;
        $totalLedger = 0;

        foreach ($warehouses as $wh) {
            $totalSubledger += $wh['subledger_value_idr'];
            $totalLedger += $wh['ledger_balance_idr'];
        }

        return [
            'module' => 'Wms',
            'entity' => $entityCode,
            'as_of_date' => $asOfDate,
            'subledger_total_idr' => $totalSubledger,
            'ledger_total_idr' => $totalLedger,
            'discrepancy_idr' => $totalSubledger - $totalLedger,
        ];
    }
}
