<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Application\Actions\GenerateDailyPolyclinicReportAction;

class AuditOutpatientCommand extends Command
{
    protected $signature = 'hospital:outpatient-audit {--date= : Tanggal audit format YYYY-MM-DD}';

    protected $description = 'Audit rekonsiliasi dua-sumber harian layanan rawat jalan: transaksi operasional vs buku besar perbankan';

    public function handle(GenerateDailyPolyclinicReportAction $action): int
    {
        $date = (string) ($this->option('date') ?: now()->toDateString());
        $this->info("Menjalankan Audit Dua-Sumber Rawat Jalan Hospital untuk tanggal {$date}...");

        $report = $action->execute($date);

        $this->table(
            ['Metrik Operasional & Rekonsiliasi', 'Nilai'],
            [
                ['Tanggal Audit', $report['date']],
                ['Jumlah Pasien Terlayani', (string) $report['total_patients']],
                ['Pendapatan Pasien Tunai (Kasir)', 'Rp '.number_format($report['cash_income_idr'], 0, ',', '.')],
                ['Klaim Piutang BPJS (INA-CBG)', 'Rp '.number_format($report['bpjs_claim_idr'], 0, ',', '.')],
                ['Total Omzet Pelayanan', 'Rp '.number_format($report['total_revenue_idr'], 0, ',', '.')],
                ['Status Rekonsiliasi Buku Besar', $report['is_ledger_reconciled'] ? 'RECONCILED' : 'DISCREPANCY DETECTED'],
            ]
        );

        if (! $report['is_ledger_reconciled']) {
            $this->error('Hospital Outpatient audit FAILED: Diskrepansi integritas data terdeteksi!');
            foreach ($report['discrepancies'] as $disc) {
                $this->line(" - {$disc}");
            }

            return self::FAILURE;
        }

        $this->info('Hospital Outpatient audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
