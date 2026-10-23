<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Resto\Contracts\RestoUsageReaderInterface;

class RestoUsageReaderService implements RestoUsageReaderInterface
{
    public function getRoyaltyPostingsForContract(string $contractId): array
    {
        return DB::table('resto_royalty_postings')
            ->where('contract_id', $contractId)
            ->get(['id', 'royalty_amount', 'date'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'royalty_amount' => (int) $p->royalty_amount,
                'date' => (string) $p->date,
            ])
            ->all();
    }

    public function getPurchaseOrdersForSupplierParties(array $partyIds): array
    {
        if (empty($partyIds)) {
            return [];
        }

        $supplierIds = DB::table('resto_suppliers')
            ->whereIn('party_id', $partyIds)
            ->pluck('id');
        if ($supplierIds->isEmpty()) {
            return [];
        }

        return DB::table('resto_purchase_orders')
            ->whereIn('supplier_id', $supplierIds)
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'grand_total', 'number'])
            ->map(fn ($po) => [
                'id' => (int) $po->id,
                'amount' => (int) $po->grand_total,
                'label' => 'PO '.$po->number,
            ])
            ->all();
    }

    public function getPurchaseOrdersForSupplierParty(string $partyId, int $limit = 20): Collection
    {
        $supplierId = DB::table('resto_suppliers')->where('party_id', $partyId)->value('id');
        if ($supplierId === null) {
            return collect();
        }

        return DB::table('resto_purchase_orders')
            ->where('supplier_id', $supplierId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'number', 'status', 'grand_total', 'expected_at']);
    }

    public function getOutletIdByCode(string $code): ?int
    {
        $id = DB::table('resto_outlets')->where('code', $code)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
