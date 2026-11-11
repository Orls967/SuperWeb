<?php

declare(strict_types=1);

namespace Modules\Logistics\Infrastructure\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Contracts\VendorExposureProviderInterface;

/**
 * Logistics Vendor Exposure Provider (W8-D57).
 *
 * Exposes freight liabilities, shipping consignments, and transport contracts
 * to the central cross-line vendor exposure aggregator.
 */
final class LogisticsVendorExposureProvider implements VendorExposureProviderInterface
{
    public function getModuleIdentifier(): string
    {
        return 'Logistics';
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

        if (Schema::hasTable('lgx_carrier_payments')) {
            $query = DB::table('lgx_carrier_payments');

            if ($tenantId !== null && Schema::hasColumn('lgx_carrier_payments', 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            $payments = $query->limit(100)->get();

            foreach ($payments as $payment) {
                $tranches[] = [
                    'module' => 'Logistics',
                    'reference' => 'PAY-'.$payment->id,
                    'amount_idr' => (int) $payment->amount_idr,
                    'status' => 'PENDING_CLEARING',
                    'currency' => 'IDR',
                    'counterparty_name' => 'Logistics Carrier #'.$payment->carrier_id,
                    'maturity_date' => null,
                    'is_contingent' => false,
                ];
            }
        }

        return $tranches;
    }
}
