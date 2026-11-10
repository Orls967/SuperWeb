<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Reports;

use Modules\Intercompany\Domain\Models\IntercompanyLoan;

/**
 * Exporter Laporan Jadwal Amortisasi Pinjaman Intercompany & Pajak Bunga (PPh 23 / 26).
 * Memuat rincian setiap angsuran, komponen pokok, bunga komersial berdasar arm's length,
 * potongan withholding tax, sisa saldo pokok, dan status pembayaran.
 */
final class IntercompanyLoanAmortizationReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(?string $loanCode = null): array
    {
        $query = IntercompanyLoan::with('schedules');
        if ($loanCode !== null && $loanCode !== '') {
            $query->where('loan_number', $loanCode);
        }
        $loans = $query->orderBy('loan_number')->get();

        $rows = [];
        $rows[] = [
            'Loan Number',
            'Lender Entity',
            'Borrower Entity',
            'Currency',
            'Principal IDR',
            'Base Rate (bps)',
            'Spread (bps)',
            'Effective Rate (bps)',
            'WHT Rate (bps)',
            'Period No',
            'Due Date',
            'Opening Balance',
            'Principal Component',
            'Gross Interest',
            'Withholding Tax (PPh23)',
            'Net Interest',
            'Total Cash Payment',
            'Closing Balance',
            'Status',
            'Ledger Tx Ref',
        ];

        $totalPrincipalDisbursed = 0;
        $totalGrossInterest = 0;
        $totalWht = 0;
        $totalPaidCash = 0;

        foreach ($loans as $loan) {
            $totalPrincipalDisbursed += (int) $loan->principal_idr;

            foreach ($loan->schedules as $sch) {
                $totalGrossInterest += (int) $sch->gross_interest_amount_idr;
                $totalWht += (int) $sch->withholding_tax_amount_idr;
                $totalPaidCash += (int) $sch->total_payment_idr;

                $rows[] = [
                    $loan->loan_number,
                    $loan->lender_entity,
                    $loan->borrower_entity,
                    $loan->currency,
                    (string) $loan->principal_idr,
                    (string) $loan->base_rate_bps,
                    (string) $loan->spread_bps,
                    (string) ($loan->base_rate_bps + $loan->spread_bps),
                    (string) $loan->withholding_tax_bps,
                    (string) $sch->period_number,
                    (string) $sch->due_date,
                    (string) $sch->opening_balance_idr,
                    (string) $sch->principal_component_idr,
                    (string) $sch->gross_interest_amount_idr,
                    (string) $sch->withholding_tax_amount_idr,
                    (string) $sch->net_interest_amount_idr,
                    (string) $sch->total_payment_idr,
                    (string) $sch->closing_balance_idr,
                    $sch->status,
                    (string) ($sch->ledger_transaction_id ?? '-'),
                ];
            }
        }

        $rows[] = [
            'SUMMARY',
            "Total Loans: {$loans->count()}",
            '',
            'IDR',
            (string) $totalPrincipalDisbursed,
            '',
            '',
            '',
            '',
            'Total Schedules: '.(count($rows) - 1),
            '',
            '',
            '',
            (string) $totalGrossInterest,
            (string) $totalWht,
            (string) ($totalGrossInterest - $totalWht),
            (string) $totalPaidCash,
            '',
            '',
            '',
        ];

        $csv = $this->buildCsv($rows);
        $fileSuffix = $loanCode !== null && $loanCode !== '' ? $loanCode : 'ALL';

        return [
            'filename' => "intercompany-loans-amortization-{$fileSuffix}.csv",
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function buildCsv(array $rows): string
    {
        $fp = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return $content ?: '';
    }
}
