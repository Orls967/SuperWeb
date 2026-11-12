<?php

declare(strict_types=1);

namespace Modules\Procurement\Infrastructure\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Contracts\VendorExposureProviderInterface;

/**
 * Procurement Vendor Exposure Provider (W8-D57).
 *
 * Exposes active purchase orders and open supplier commitments
 * to the central cross-line vendor exposure aggregator.
 */
final class ProcurementVendorExposureProvider implements VendorExposureProviderInterface
{
    public function getModuleIdentifier(): string
    {
        return 'Procurement';
    }

    /**
     * @return list<array{
     *     module: string,
     *     reference: string,
     *     amount_idr: int,
     *     status: string,
     *     currency?: string,
     *     counterparty_name?: string,
     *     maturity_date?: ?string,
     *     is_contingent?: bool
     * }>
     */
    public function collectExposureTranches(string $vendorClusterCode, ?string $tenantId = null): array
    {
        $tranches = [];

        if (Schema::hasTable('prc_purchase_orders')) {
            $query = DB::table('prc_purchase_orders')
                ->where('vendor_cluster_code', $vendorClusterCode)
                ->whereIn('status', ['APPROVED', 'ISSUED', 'PARTIALLY_DELIVERED']);

            if ($tenantId !== null && Schema::hasColumn('prc_purchase_orders', 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            $orders = $query->limit(100)->get();

            foreach ($orders as $order) {
                $tranches[] = [
                    'module' => 'Procurement',
                    'reference' => (string) ($order->po_number ?? ('PO-'.$order->id)),
                    'amount_idr' => (int) ($order->total_amount_idr ?? ($order->total_amount ?? 0)),
                    'status' => (string) ($order->status ?? 'COMMITTED'),
                    'currency' => 'IDR',
                    'counterparty_name' => (string) ($order->vendor_name ?? 'Procurement Vendor'),
                    'maturity_date' => isset($order->delivery_due_date) ? (string) $order->delivery_due_date : null,
                    'is_contingent' => false,
                ];
            }
        }

        return $tranches;
    }
}
