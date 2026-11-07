<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\CityLedgerAccount;
use Modules\Hotel\Domain\Models\CityLedgerTransfer;

/**
 * Tindakan Rekonsiliasi Piutang Korporat & Analisis Umur Piutang (Aging Schedule).
 */
final class ReconcileCityLedgerCorporateAgingAction
{
    /**
     * @return array{account_id: string, company_name: string, total_outstanding_idr: int, current_30_days_idr: int, overdue_60_days_idr: int, overdue_90_days_idr: int}
     */
    public function execute(string $cityLedgerAccountId): array
    {
        $account = CityLedgerAccount::findOrFail($cityLedgerAccountId);

        $transfers = CityLedgerTransfer::where('city_ledger_account_id', $account->id)->get();

        $current30 = 0;
        $overdue60 = 0;
        $overdue90 = 0;

        $now = now();

        foreach ($transfers as $t) {
            $days = $t->created_at ? $now->diffInDays($t->created_at) : 0;
            $amt = (int) $t->transferred_amount_idr;

            if ($days <= 30) {
                $current30 += $amt;
            } elseif ($days <= 60) {
                $overdue60 += $amt;
            } else {
                $overdue90 += $amt;
            }
        }

        $totalOutstanding = (int) $account->outstanding_balance_idr;

        return [
            'account_id' => $account->id,
            'company_name' => $account->company_name,
            'total_outstanding_idr' => $totalOutstanding,
            'current_30_days_idr' => $current30,
            'overdue_60_days_idr' => $overdue60,
            'overdue_90_days_idr' => $overdue90,
        ];
    }
}
