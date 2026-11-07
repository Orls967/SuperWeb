<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Venue\Domain\Models\SecondaryLedgerEntry;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;

final class SecondaryMarketComplianceAuditService
{
    /**
     * Jalankan audit komprehensif kepatuhan regulasi perpajakan daerah dan perlindungan konsumen.
     *
     * @return array{
     *   total_audited_transactions: int,
     *   compliance_rate_bps: int,
     *   violations_count: int,
     *   total_tax_collected_idr: int,
     *   total_platform_fees_idr: int,
     *   total_seller_disbursements_idr: int,
     *   zero_leakage_verified: bool,
     *   audit_findings: array<int, string>
     * }
     */
    public function auditRegulatoryCompliance(): array
    {
        $findings = [];
        $settledResales = TicketResale::query()
            ->where('status', TicketResaleStateMachine::STATUS_SETTLED)
            ->get();

        $totalAudited = $settledResales->count();
        $violations = 0;
        $totalTax = 0;
        $totalFees = 0;
        $totalDisbursements = 0;

        foreach ($settledResales as $resale) {
            $faceValue = $resale->original_face_value_idr;
            $resalePrice = $resale->resale_price_idr;
            $maxAllowed = (int) intdiv($faceValue * 120, 100);

            // 1. Verifikasi Batas Cap 120%
            if ($resalePrice > $maxAllowed) {
                $violations++;
                $findings[] = sprintf(
                    'PELANGGARAN_CAP_HARGA: Resale #%d memiliki harga Rp %d melampaui plafon Rp %d (Face Value: Rp %d)',
                    $resale->id,
                    $resalePrice,
                    $maxAllowed,
                    $faceValue
                );
            }

            // 2. Verifikasi Konservasi Moneter Tertutup
            $pbjt = $resale->tax_pbjt_idr;
            $fee = $resale->platform_fee_idr;
            $royalty = $resale->promoter_royalty_idr;
            $sellerPayout = $resale->seller_payout_idr;

            $sumComponents = $pbjt + $fee + $royalty + $sellerPayout;
            if ($sumComponents !== $resalePrice) {
                $violations++;
                $findings[] = sprintf(
                    'INKONSISTENSI_MONETER: Resale #%d memiliki selisih pembagian Rp %d (%d != %d)',
                    $resale->id,
                    $resalePrice - $sumComponents,
                    $sumComponents,
                    $resalePrice
                );
            }

            // 3. Verifikasi Keberadaan Token Barcode Berputar
            if (empty($resale->new_ticket_hash) || strlen((string) $resale->new_ticket_hash) !== 64) {
                $violations++;
                $findings[] = sprintf(
                    'TOKEN_BARCODE_TIDAK_VALID: Resale #%d tidak memiliki hash barcode rotasi 64-karakter yang sah',
                    $resale->id
                );
            }

            $totalTax += $pbjt;
            $totalFees += $fee;
            $totalDisbursements += $sellerPayout;
        }

        $complianceRateBps = $totalAudited > 0
            ? (int) intdiv(($totalAudited - $violations) * 10000, $totalAudited)
            : 10000;

        return [
            'total_audited_transactions' => $totalAudited,
            'compliance_rate_bps' => max(0, $complianceRateBps),
            'violations_count' => $violations,
            'total_tax_collected_idr' => $totalTax,
            'total_platform_fees_idr' => $totalFees,
            'total_seller_disbursements_idr' => $totalDisbursements,
            'zero_leakage_verified' => $violations === 0,
            'audit_findings' => $findings,
        ];
    }

    /**
     * Rekonsiliasi buku besar escrow pasar sekunder (Zero Ledger Leakage).
     *
     * @return array{
     *   total_ledger_entries: int,
     *   sum_debit_idr: int,
     *   sum_credit_idr: int,
     *   balanced: bool,
     *   discrepancy_idr: int,
     *   unmatched_resales: array<int, int>
     * }
     */
    public function auditEscrowLedgerReconciliation(): array
    {
        $entries = SecondaryLedgerEntry::query()->get();
        $totalEntries = $entries->count();

        $sumDebit = (int) $entries->sum('debit_amount_idr');
        $sumCredit = (int) $entries->sum('credit_amount_idr');
        $discrepancy = abs($sumDebit - $sumCredit);

        // Cari transaksi resale yang tidak memiliki pasangan debit-kredit seimbang
        $unmatchedResales = [];
        $entriesByResale = $entries->groupBy('ticket_resale_id');

        foreach ($entriesByResale as $resaleId => $group) {
            $groupDebit = (int) $group->sum('debit_amount_idr');
            $groupCredit = (int) $group->sum('credit_amount_idr');
            if ($groupDebit !== $groupCredit && $resaleId !== null) {
                $unmatchedResales[] = (int) $resaleId;
            }
        }

        return [
            'total_ledger_entries' => $totalEntries,
            'sum_debit_idr' => $sumDebit,
            'sum_credit_idr' => $sumCredit,
            'balanced' => $discrepancy === 0,
            'discrepancy_idr' => $discrepancy,
            'unmatched_resales' => $unmatchedResales,
        ];
    }

    /**
     * Audit Anti-Money Laundering (AML) dan velocity pembatasan tiket per individu.
     * Regulasi menetapkan maksimal 4 tiket sekunder per pembeli/penjual dalam rentang 30 hari.
     *
     * @return array{
     *   flagged_users: array<int, array{user_id: int, total_resales: int, total_volume_idr: int, reason: string}>,
     *   aml_risk_level: string,
     *   total_suspicious_volume_idr: int
     * }
     */
    public function auditSellerVelocityAndAml(int $maxTicketsPerUser = 4, int $lookbackDays = 30): array
    {
        $since = Carbon::now()->subDays($lookbackDays);
        $resales = TicketResale::query()
            ->where('created_at', '>=', $since)
            ->whereIn('status', [TicketResaleStateMachine::STATUS_LISTED, TicketResaleStateMachine::STATUS_ESCROW_HELD, TicketResaleStateMachine::STATUS_SETTLED])
            ->get();

        $flaggedUsers = [];
        $totalSuspiciousVolume = 0;

        $groupedBySeller = $resales->groupBy('seller_user_id');
        foreach ($groupedBySeller as $sellerId => $sellerResales) {
            $count = $sellerResales->count();
            $volume = (int) $sellerResales->sum('resale_price_idr');

            if ($count > $maxTicketsPerUser) {
                $flaggedUsers[] = [
                    'user_id' => (int) $sellerId,
                    'total_resales' => $count,
                    'total_volume_idr' => $volume,
                    'reason' => sprintf('Melampaui batas velocity jual: %d tiket (maksimum %d)', $count, $maxTicketsPerUser),
                ];
                $totalSuspiciousVolume += $volume;
            }
        }

        $groupedByBuyer = $resales->whereNotNull('buyer_user_id')->groupBy('buyer_user_id');
        foreach ($groupedByBuyer as $buyerId => $buyerResales) {
            $count = $buyerResales->count();
            $volume = (int) $buyerResales->sum('resale_price_idr');

            if ($count > $maxTicketsPerUser) {
                $flaggedUsers[] = [
                    'user_id' => (int) $buyerId,
                    'total_resales' => $count,
                    'total_volume_idr' => $volume,
                    'reason' => sprintf('Melampaui batas velocity beli: %d tiket (maksimum %d)', $count, $maxTicketsPerUser),
                ];
                $totalSuspiciousVolume += $volume;
            }
        }

        $riskLevel = 'LOW';
        if (count($flaggedUsers) > 10 || $totalSuspiciousVolume > 100_000_000) {
            $riskLevel = 'CRITICAL';
        } elseif (count($flaggedUsers) > 3 || $totalSuspiciousVolume > 30_000_000) {
            $riskLevel = 'MEDIUM';
        }

        return [
            'flagged_users' => $flaggedUsers,
            'aml_risk_level' => $riskLevel,
            'total_suspicious_volume_idr' => $totalSuspiciousVolume,
        ];
    }

    /**
     * Hasilkan laporan pelaporan pajak PBJT resmi untuk Badan Pendapatan Daerah (Bapenda).
     *
     * @return array{
     *   reporting_period_start: string,
     *   reporting_period_end: string,
     *   total_transactions: int,
     *   gross_secondary_turnover_idr: int,
     *   taxable_turnover_idr: int,
     *   pbjt_rate_percentage: float,
     *   total_pbjt_liability_idr: int,
     *   withheld_in_escrow_idr: int,
     *   settlement_status: string,
     *   generated_at: string
     * }
     */
    public function generateBapendaFilingReport(Carbon $start, Carbon $end): array
    {
        $settledResales = TicketResale::query()
            ->where('status', TicketResaleStateMachine::STATUS_SETTLED)
            ->whereBetween('settled_at', [$start, $end])
            ->get();

        $totalTransactions = $settledResales->count();
        $grossTurnover = (int) $settledResales->sum('resale_price_idr');
        $totalPbjt = (int) $settledResales->sum('tax_pbjt_idr');

        return [
            'reporting_period_start' => $start->toIso8601String(),
            'reporting_period_end' => $end->toIso8601String(),
            'total_transactions' => $totalTransactions,
            'gross_secondary_turnover_idr' => $grossTurnover,
            'taxable_turnover_idr' => $grossTurnover,
            'pbjt_rate_percentage' => 10.0,
            'total_pbjt_liability_idr' => $totalPbjt,
            'withheld_in_escrow_idr' => $totalPbjt,
            'settlement_status' => $totalTransactions > 0 ? 'RECONCILED_READY_FOR_REMITTANCE' : 'NO_TRANSACTIONS',
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }
}
