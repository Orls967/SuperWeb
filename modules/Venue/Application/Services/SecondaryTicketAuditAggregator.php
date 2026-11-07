<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Models\SecondaryLedgerEntry;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;

final class SecondaryTicketAuditAggregator
{
    /**
     * Jalankan agregasi audit menyeluruh integritas pasar sekunder untuk mencegah fraud finansial dan kebocoran pajak.
     *
     * @return array{
     *   total_resale_listings: int,
     *   settled_resales: int,
     *   total_turnover_idr: int,
     *   total_pbjt_tax_idr: int,
     *   total_platform_fee_idr: int,
     *   total_promoter_royalty_idr: int,
     *   total_seller_payout_idr: int,
     *   is_audit_healthy: bool,
     *   discrepancies: array<int, string>
     * }
     */
    public function performFullMarketAudit(): array
    {
        $discrepancies = [];

        $allListings = TicketResale::query()->get();
        $settledListings = $allListings->where('status', TicketResaleStateMachine::STATUS_SETTLED);

        $totalTurnover = (int) $settledListings->sum('resale_price_idr');
        $totalPbjt = (int) $settledListings->sum('pbjt_tax_idr');
        $totalPlatform = (int) $settledListings->sum('platform_fee_idr');
        $totalRoyalty = (int) $settledListings->sum('promoter_royalty_idr');
        $totalSellerPayout = (int) $settledListings->sum('seller_payout_idr');

        $computedSum = $totalPbjt + $totalPlatform + $totalRoyalty + $totalSellerPayout;

        if ($totalTurnover !== $computedSum) {
            $discrepancies[] = sprintf(
                'Audit Mismatch: Total turnover (%d) != Jumlah komponen (%d). Selisih: %d',
                $totalTurnover,
                $computedSum,
                $totalTurnover - $computedSum
            );
        }

        // Cek ledger entry vs database resale
        $ledgerEntries = SecondaryLedgerEntry::query()->where('entry_type', 'SECONDARY_SETTLEMENT')->get();
        $ledgerGross = (int) $ledgerEntries->sum('gross_amount_idr');

        if ($ledgerEntries->count() > 0 && $ledgerGross !== $totalTurnover) {
            $discrepancies[] = sprintf(
                'Ledger Inconsistency: Gross turnover di tabel resale (%d) != Gross di secondary ledger (%d)',
                $totalTurnover,
                $ledgerGross
            );
        }

        // Cek aturan single transfer violation
        $duplicateTransfers = TicketResale::query()
            ->select('ticket_id', DB::raw('count(*) as settled_count'))
            ->where('status', TicketResaleStateMachine::STATUS_SETTLED)
            ->groupBy('ticket_id')
            ->having('settled_count', '>', 1)
            ->get();

        if ($duplicateTransfers->isNotEmpty()) {
            foreach ($duplicateTransfers as $dup) {
                $discrepancies[] = "Single Transfer Violation: Tiket #{$dup->ticket_id} dipindahtangankan {$dup->settled_count} kali.";
            }
        }

        return [
            'total_resale_listings' => $allListings->count(),
            'settled_resales' => $settledListings->count(),
            'total_turnover_idr' => $totalTurnover,
            'total_pbjt_tax_idr' => $totalPbjt,
            'total_platform_fee_idr' => $totalPlatform,
            'total_promoter_royalty_idr' => $totalRoyalty,
            'total_seller_payout_idr' => $totalSellerPayout,
            'is_audit_healthy' => count($discrepancies) === 0,
            'discrepancies' => $discrepancies,
        ];
    }
}
