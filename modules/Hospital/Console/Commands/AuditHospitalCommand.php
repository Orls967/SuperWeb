<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Application\Actions\GenerateDailyPolyclinicReportAction;
use Modules\Hospital\Application\Services\ClinicalTrialAndResearchService;
use Modules\Hospital\Domain\Models\OutpatientEncounter;

class AuditHospitalCommand extends Command
{
    protected $signature = 'hsp:audit {--date= : Tanggal audit rawat jalan format YYYY-MM-DD}';

    protected $aliases = ['hosp:audit'];

    protected $description = 'Audit layanan operasional rumah sakit: kapasitas ranjang, pendaftaran pasien, uji klinis, integritas subjek, dan rekonsiliasi dua-sumber rawat jalan';

    public function handle(
        ClinicalTrialAndResearchService $service,
        GenerateDailyPolyclinicReportAction $reportAction
    ): int {
        $this->info('Starting Hospital & Clinical Trial Operations Audit (hsp:audit)...');

        $result = $service->auditHospital();
        $discrepancies = $result['discrepancies'];

        // Audit rekonsiliasi dua-sumber rawat jalan (dilebur dari hospital:outpatient-audit)
        $dateOption = $this->option('date');
        $outpatientDiscrepancies = [];

        if (! empty($dateOption)) {
            $dates = [(string) $dateOption];
        } else {
            $dates = OutpatientEncounter::selectRaw('DATE(created_at) as enc_date')
                ->distinct()
                ->pluck('enc_date')
                ->filter()
                ->map(fn ($d) => (string) $d)
                ->values()
                ->all();

            if (empty($dates)) {
                $dates = [now()->toDateString()];
            }
        }

        foreach ($dates as $auditDate) {
            $report = $reportAction->execute($auditDate);
            if (! $report['is_ledger_reconciled']) {
                foreach ($report['discrepancies'] as $disc) {
                    $outpatientDiscrepancies[] = "[{$auditDate}] {$disc}";
                    $discrepancies++;
                }
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY'],
                ['Clinical Trials', $result['trial_count']],
                ['Trial Subjects', $result['subject_count']],
                ['Patients Registered', $result['patient_count']],
                ['Hospital Beds', $result['bed_count']],
                ['Outpatient Dates Audited', count($dates)],
                ['Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Hospital audit FAILED with {$discrepancies} discrepancies detected!");
            foreach ($outpatientDiscrepancies as $disc) {
                $this->line(" - {$disc}");
            }

            return self::FAILURE;
        }

        $this->info('Hospital audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
