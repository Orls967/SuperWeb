<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Domain\Services\ClaimAgingAnalysisService;

class ClaimAgingReportCommand extends Command
{
    protected $signature = 'hsp:claim-aging-report {--payer= : Filter jenis penjamin klaim (BPJS / PRIVATE_INSURANCE)}';

    protected $description = 'Menghasilkan laporan umur piutang klaim asuransi & estimasi cadangan kerugian penurunan nilai';

    public function handle(ClaimAgingAnalysisService $agingService): int
    {
        $this->info('Menghasilkan Laporan Umur Piutang Klaim (Aging Analysis)...');

        $payer = $this->option('payer');
        $summary = $agingService->generateAgingSummary($payer ? (string) $payer : null);

        $this->table(
            ['Bucket Umur Piutang', 'Jumlah Tertunggak (IDR)', 'Keterangan Risiko'],
            [
                ['Lancar (0 - 30 hari)', 'Rp '.number_format($summary->current0To30Idr, 0, ',', '.'), 'Risiko Rendah (ECL 1%)'],
                ['Kurang Lancar (31 - 60 hari)', 'Rp '.number_format($summary->pastDue31To60Idr, 0, ',', '.'), 'Perlu Follow-up (ECL 5%)'],
                ['Diragukan (61 - 90 hari)', 'Rp '.number_format($summary->pastDue61To90Idr, 0, ',', '.'), 'Dispute / Klarifikasi (ECL 20%)'],
                ['Macet (> 90 hari)', 'Rp '.number_format($summary->pastDueOver90Idr, 0, ',', '.'), 'Kritis / Write-Off (ECL 50%)'],
            ]
        );

        $this->info('Total Piutang Klaim Beredar: Rp '.number_format($summary->totalOutstandingIdr, 0, ',', '.'));
        $this->warn('Estimasi Provisi Kerugian (Allowance): Rp '.number_format($summary->estimatedImpairmentAllowanceIdr, 0, ',', '.'));

        $urgentInterventions = $agingService->getClaimsRequiringDisputeIntervention();
        if (! empty($urgentInterventions)) {
            $this->error('Ditemukan '.count($urgentInterventions).' klaim membutuhkan intervensi banding atau klarifikasi koding segera.');
        }

        return self::SUCCESS;
    }
}
