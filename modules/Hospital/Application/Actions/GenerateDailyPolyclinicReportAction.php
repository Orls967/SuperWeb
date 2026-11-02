<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Polyclinic;

class GenerateDailyPolyclinicReportAction
{
    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

    /**
     * @return array{
     *     date: string,
     *     total_patients: int,
     *     cash_income_idr: int,
     *     bpjs_claim_idr: int,
     *     total_revenue_idr: int,
     *     is_ledger_reconciled: bool,
     *     discrepancies: list<string>,
     *     by_polyclinic: list<array{polyclinic: string, patient_count: int, revenue_idr: int}>
     * }
     */
    public function execute(string $date): array
    {
        $totalPatients = OutpatientEncounter::whereDate('created_at', $date)
            ->where('status', '!=', 'CANCELLED')
            ->count();

        $cashIncome = (int) OutpatientInvoice::where('payment_method', 'CASH')
            ->where('status', 'PAID')
            ->whereDate('created_at', $date)
            ->sum('total_amount_idr');

        $bpjsClaim = (int) BpjsClaim::whereDate('created_at', $date)
            ->sum('ina_cbg_tariff_idr');

        $totalRevenue = $cashIncome + $bpjsClaim;

        // Breakdown per polyclinic
        $byPolyclinic = [];
        $polyclinics = Polyclinic::all();
        foreach ($polyclinics as $poli) {
            $count = OutpatientEncounter::where('polyclinic_id', $poli->id)
                ->whereDate('created_at', $date)
                ->where('status', '!=', 'CANCELLED')
                ->count();

            $rev = (int) OutpatientInvoice::whereHas('encounter', function ($q) use ($poli) {
                $q->where('polyclinic_id', $poli->id);
            })->whereDate('created_at', $date)->sum('total_amount_idr');

            $byPolyclinic[] = [
                'polyclinic' => $poli->name,
                'patient_count' => $count,
                'revenue_idr' => $rev,
            ];
        }

        // Rekonsiliasi dua-sumber terhadap mutasi Buku Besar Banking pada tanggal lokal
        $discrepancies = [];
        $startOfDay = $date.' 00:00:00';
        $endOfDay = $date.' 23:59:59';

        // 1. Cek Kas Masuk via entri ledger pada tanggal lokal (sesuai sisi normal debit = negatif per §A2.1)
        $cashRow = DB::selectOne('
            SELECT COALESCE(SUM(le.amount), 0) AS total_sum
            FROM bank_ledger_entries le
            JOIN bank_ledger_transactions lt ON le.transaction_id = lt.id
            JOIN bank_ledger_accounts la ON le.account_id = la.id
            WHERE la.code = ? AND lt.posted_at >= ? AND lt.posted_at <= ?
        ', ['hsp:cash:IDR', $startOfDay, $endOfDay]);

        $ledgerCashSum = (int) ($cashRow?->total_sum ?? 0);
        $ledgerCashDebit = -$ledgerCashSum;
        if ($ledgerCashDebit !== $cashIncome) {
            $discrepancies[] = "Diskrepansi Kas Tunai: Operasional Rp {$cashIncome} vs Ledger Rp {$ledgerCashDebit}";
        }

        // 2. Cek Piutang BPJS via entri ledger pada tanggal lokal (sesuai sisi normal debit = negatif per §A2.1)
        $bpjsRow = DB::selectOne('
            SELECT COALESCE(SUM(le.amount), 0) AS total_sum
            FROM bank_ledger_entries le
            JOIN bank_ledger_transactions lt ON le.transaction_id = lt.id
            JOIN bank_ledger_accounts la ON le.account_id = la.id
            WHERE la.code = ? AND lt.posted_at >= ? AND lt.posted_at <= ?
        ', ['hsp:bpjs_receivable:IDR', $startOfDay, $endOfDay]);

        $ledgerBpjsSum = (int) ($bpjsRow?->total_sum ?? 0);
        $ledgerBpjsDebit = -$ledgerBpjsSum;
        if ($ledgerBpjsDebit !== $bpjsClaim) {
            $discrepancies[] = "Diskrepansi Piutang BPJS: Operasional Rp {$bpjsClaim} vs Ledger Rp {$ledgerBpjsDebit}";
        }

        // 3. Verifikasi konsistensi invoice terhadap payment
        $corruptedInvoices = OutpatientInvoice::whereDate('created_at', $date)
            ->where('payment_method', 'CASH')
            ->where('status', 'PAID')
            ->whereDoesntHave('payments', function ($q) {
                $q->whereRaw('hsp_outpatient_payments.amount_idr = hsp_outpatient_invoices.total_amount_idr');
            })
            ->count();

        if ($corruptedInvoices > 0) {
            $discrepancies[] = "Ditemukan {$corruptedInvoices} tagihan kasir termanipulasi atau tidak cocok dengan kuitansi pembayaran.";
        }

        return [
            'date' => $date,
            'total_patients' => $totalPatients,
            'cash_income_idr' => $cashIncome,
            'bpjs_claim_idr' => $bpjsClaim,
            'total_revenue_idr' => $totalRevenue,
            'is_ledger_reconciled' => count($discrepancies) === 0,
            'discrepancies' => $discrepancies,
            'by_polyclinic' => $byPolyclinic,
        ];
    }
}
