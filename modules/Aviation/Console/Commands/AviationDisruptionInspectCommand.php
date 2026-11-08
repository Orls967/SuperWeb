<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Application\Services\AviationIrropsService;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Exports\IrropsManifestCsvExporter;

class AviationDisruptionInspectCommand extends Command
{
    protected $signature = 'avi:disruption-inspect
                            {case_id? : ID kasus disrupsi untuk diinspeksi}
                            {--export : Ekspor manifes penumpang ke format CSV}';

    protected $description = 'Inspeksi kasus disrupsi IRROPS dan verifikasi kepatuhan regulasi PM 89';

    public function handle(
        AviationIrropsService $irropsService,
        IrropsManifestCsvExporter $csvExporter
    ): int {
        $caseId = $this->argument('case_id');

        if ($caseId === null) {
            $cases = AviationDisruptionCase::with('flight')->latest()->take(10)->get();
            $this->table(
                ['ID', 'No Kasus', 'No Flight', 'Kategori', 'Delay', 'PM89', 'Status'],
                $cases->map(fn ($c) => [
                    $c->id,
                    $c->case_number,
                    $c->flight->flight_number ?? 'N/A',
                    $c->incident_category,
                    $c->delay_minutes.' mnt',
                    $c->regulatory_category,
                    $c->status,
                ])
            );

            return 0;
        }

        $id = (int) $caseId;

        if ($this->option('export')) {
            $csv = $csvExporter->exportCaseManifest($id);
            $this->line($csv);

            return 0;
        }

        $summary = $irropsService->getDisruptionCaseSummary($id);
        $this->info("=== Ringkasan Kasus Disrupsi [{$summary['case_number']}] ===");
        $this->line("- Penerbangan: {$summary['flight_number']}");
        $this->line("- Status Kasus: {$summary['status']}");
        $this->line("- Kategori Insiden: {$summary['incident_category']} (Delay: {$summary['delay_minutes']} mnt)");
        $this->line("- Kategori Regulasi PM 89: {$summary['regulatory_category']}");
        $this->line("- Total Penumpang: {$summary['total_passengers']}");
        $this->line("- E-Voucher Terbit: {$summary['total_vouchers_issued']} (Ditebus: {$summary['total_vouchers_redeemed']})");
        $this->line('- Fasilitas Hotel: '.($summary['hotel_required'] ? 'YA' : 'TIDAK'));
        $this->line("- Klaim Asuransi Parametrik: {$summary['parametric_claims_count']}");

        return 0;
    }
}
