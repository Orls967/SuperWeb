<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Modules\Intercompany\Contracts\SubledgerTotalProvider;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\UnrealizedInventoryProfit;

class DefaultSubledgerTotalProvider implements SubledgerTotalProvider
{
    public function getTotalReceivableMinor(string $entityCode, string $currency = 'IDR'): int
    {
        return (int) IntercompanyTransaction::where('selling_entity', $entityCode)
            ->where('currency', strtoupper($currency))
            ->whereNotIn('status', ['reconciled', 'eliminated', 'reversed'])
            ->sum('amount_idr');
    }

    public function getTotalPayableMinor(string $entityCode, string $currency = 'IDR'): int
    {
        return (int) IntercompanyTransaction::where('buying_entity', $entityCode)
            ->where('currency', strtoupper($currency))
            ->whereNotIn('status', ['reconciled', 'eliminated', 'reversed'])
            ->sum('amount_idr');
    }

    public function getBilateralReciprocalSummary(string $sellerEntity, string $buyerEntity, string $currency = 'IDR'): array
    {
        $ar = (int) IntercompanyTransaction::where('selling_entity', $sellerEntity)
            ->where('buying_entity', $buyerEntity)
            ->where('currency', strtoupper($currency))
            ->whereNotIn('status', ['reconciled', 'eliminated', 'reversed'])
            ->sum('amount_idr');

        $ap = (int) IntercompanyTransaction::where('selling_entity', $sellerEntity)
            ->where('buying_entity', $buyerEntity)
            ->where('currency', strtoupper($currency))
            ->whereNotIn('status', ['reconciled', 'eliminated', 'reversed'])
            ->sum('amount_idr');

        $discrepancy = abs($ar - $ap);

        return [
            'seller_entity' => $sellerEntity,
            'buyer_entity' => $buyerEntity,
            'receivable_minor' => $ar,
            'payable_minor' => $ap,
            'net_discrepancy_minor' => $discrepancy,
            'status' => $discrepancy === 0 ? 'MATCHED' : 'DISCREPANCY',
        ];
    }

    public function getUnrealizedInventoryProfitTotalIdr(string $period): int
    {
        return (int) UnrealizedInventoryProfit::where('period', $period)
            ->where('status', 'eliminated')
            ->sum('unrealized_profit_idr');
    }
}
