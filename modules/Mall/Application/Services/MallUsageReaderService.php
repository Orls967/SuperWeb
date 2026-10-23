<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Mall\Contracts\MallUsageReaderInterface;

class MallUsageReaderService implements MallUsageReaderInterface
{
    public function getTenantOutstandingExposure(string $partyId): int
    {
        return (int) DB::table('mall_invoices')
            ->join('mall_tenants', 'mall_invoices.tenant_id', '=', 'mall_tenants.id')
            ->where('mall_tenants.party_id', $partyId)
            ->whereIn('mall_invoices.status', ['issued', 'partially_paid', 'overdue'])
            ->sum('mall_invoices.total_amount');
    }

    public function getInvoicesForLease(int $leaseId): array
    {
        return DB::table('mall_invoices')
            ->where('lease_id', $leaseId)
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'total_amount', 'invoice_number'])
            ->map(fn ($inv) => [
                'id' => (int) $inv->id,
                'total_amount' => (int) $inv->total_amount,
                'invoice_number' => (string) $inv->invoice_number,
            ])
            ->all();
    }
}
