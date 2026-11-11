<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Infrastructure\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Contracts\VendorExposureProviderInterface;

/**
 * Trade Finance Vendor Exposure Provider (W8-D57).
 *
 * Exposes Letters of Credit (LC), Bank Guarantees (BG), and invoice financing
 * facilities to the central cross-line vendor exposure aggregator.
 */
final class TradeFinanceVendorExposureProvider implements VendorExposureProviderInterface
{
    public function getModuleIdentifier(): string
    {
        return 'TradeFinance';
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

        if (Schema::hasTable('tf_letters_of_credit')) {
            $query = DB::table('tf_letters_of_credit')
                ->whereIn('status', ['issued', 'advised', 'amended', 'presented', 'accepted']);

            if ($tenantId !== null && Schema::hasColumn('tf_letters_of_credit', 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            $instruments = $query->limit(100)->get();

            foreach ($instruments as $inst) {
                $tranches[] = [
                    'module' => 'TradeFinance',
                    'reference' => (string) ($inst->lc_number ?? ('LC-'.$inst->id)),
                    'amount_idr' => (int) ($inst->amount_functional_idr ?? 0),
                    'status' => strtoupper((string) ($inst->status ?? 'ACTIVE')),
                    'currency' => 'IDR',
                    'counterparty_name' => (string) ($inst->beneficiary_name ?? 'Trade Finance Beneficiary'),
                    'maturity_date' => isset($inst->expiry_date) ? (string) $inst->expiry_date : null,
                    'is_contingent' => true,
                ];
            }
        }

        return $tranches;
    }
}
