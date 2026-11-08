<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Application\Services\AviationCodeshareService;
use Modules\Aviation\Exports\CodeshareClearingCsvExporter;

class AviationCodeshareProrateCommand extends Command
{
    protected $signature = 'avi:codeshare-prorate
                            {agreement_id : ID perjanjian codeshare}
                            {ticket_id? : ID tiket spesifik untuk diselesaikan}
                            {--distance=600 : Jarak terbang segmen dalam TPM}
                            {--export : Ekspor ringkasan kliring ke CSV}';

    protected $description = 'Selesaikan pembagian pendapatan tiket codeshare dan ekspor berkas kliring ICH';

    public function handle(
        AviationCodeshareService $codeshareService,
        CodeshareClearingCsvExporter $csvExporter
    ): int {
        $agreementId = (int) $this->argument('agreement_id');
        $ticketId = $this->argument('ticket_id');

        if ($this->option('export')) {
            $csv = $csvExporter->exportAgreementSummary($agreementId);
            $this->line($csv);

            return 0;
        }

        if ($ticketId === null) {
            $this->error('ID tiket diperlukan untuk menyelesaikan transaksi prorasi.');

            return 1;
        }

        $distance = (int) $this->option('distance');
        $this->info("Menyelesaikan kliring tiket [{$ticketId}] pada perjanjian [{$agreementId}]...");

        $res = $codeshareService->settleTicket($agreementId, (int) $ticketId, $distance);

        $this->info("Penyelesaian kliring berhasil (Saga ID: {$res['saga_id']})");
        $this->line('- Operating Share: Rp'.number_format($res['details']['operating_share_minor_unit'] / 100, 0, ',', '.'));
        $this->line('- Marketing Share: Rp'.number_format($res['details']['marketing_share_minor_unit'] / 100, 0, ',', '.'));
        $this->line("- Miles Diakrual: {$res['details']['miles_accrued']}");

        return 0;
    }
}
