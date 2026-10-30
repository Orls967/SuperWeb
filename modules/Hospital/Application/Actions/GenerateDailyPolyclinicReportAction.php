<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Polyclinic;

class GenerateDailyPolyclinicReportAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

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

        // Rekonsiliasi dua-sumber terhadap saldo mutasi Buku Besar Banking
        $discrepancies = [];

        // 1. Cek Kas Masuk via LedgerService
        $cashBalanceStr = $this->ledgerService->getCachedBalance('hsp:cash:IDR');
        $ledgerCashDebit = $cashBalanceStr !== null ? abs((int) (float) $cashBalanceStr) : 0;
        if ($ledgerCashDebit !== $cashIncome) {
            $discrepancies[] = "Diskrepansi Kas Tunai: Operasional Rp {$cashIncome} vs Ledger Rp {$ledgerCashDebit}";
        }

        // 2. Cek Piutang BPJS via LedgerService
        $bpjsBalanceStr = $this->ledgerService->getCachedBalance('hsp:bpjs_receivable:IDR');
        $ledgerBpjsDebit = $bpjsBalanceStr !== null ? abs((int) (float) $bpjsBalanceStr) : 0;
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
