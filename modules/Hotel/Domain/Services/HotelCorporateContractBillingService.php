<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\CityLedgerAccount;

/**
 * Layanan Domain: Perjanjian Kontrak Korporat, Pagu Kredit & Penagihan Langsung (City Ledger).
 */
final class HotelCorporateContractBillingService
{
    /**
     * @return array{company_code: string, credit_limit_idr: int, outstanding_idr: int, available_credit_idr: int, credit_health: string}
     */
    public function evaluateCreditHealth(string $cityLedgerAccountId): array
    {
        $account = CityLedgerAccount::findOrFail($cityLedgerAccountId);

        $limit = (int) $account->credit_limit_idr;
        $outstanding = (int) $account->outstanding_balance_idr;
        $available = max(0, $limit - $outstanding);

        $utilizationPct = $limit > 0 ? ($outstanding / (float) $limit) * 100.0 : 0.0;

        $health = match (true) {
            $utilizationPct >= 90.0 => 'CRITICAL_LIMIT_EXCEEDED',
            $utilizationPct >= 70.0 => 'WARNING_NEAR_LIMIT',
            default => 'GOOD_STANDING',
        };

        return [
            'company_code' => $account->company_code,
            'credit_limit_idr' => $limit,
            'outstanding_idr' => $outstanding,
            'available_credit_idr' => $available,
            'credit_health' => $health,
        ];
    }
}
