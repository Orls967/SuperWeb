<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Infrastructure\Adapters;

use Modules\Intercompany\Contracts\SubledgerTotalProvider;

class InventorySubledgerTotalAdapter implements SubledgerTotalProvider
{
    public function getModuleName(): string
    {
        return 'Inventory';
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
        return [
            'module' => 'Inventory',
            'entity' => $entityCode,
            'total_inventory_valuation_minor' => 0,
            'discrepancy_minor' => 0,
        ];
    }
}
