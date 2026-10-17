<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Application\Services\ClinicalTrialAndResearchService;

class AuditHospitalCommand extends Command
{
    protected $signature = 'hsp:audit';

    protected $description = 'Audit layanan operasional rumah sakit: kapasitas ranjang, pendaftaran pasien, uji klinis, dan integritas subjek';

    public function handle(ClinicalTrialAndResearchService $service): int
    {
        $this->info('Starting Hospital & Clinical Trial Operations Audit (hsp:audit)...');

        $result = $service->auditHospital();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $result['status']],
                ['Clinical Trials', $result['trial_count']],
                ['Trial Subjects', $result['subject_count']],
                ['Patients Registered', $result['patient_count']],
                ['Hospital Beds', $result['bed_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Hospital audit FAILED with {$result['discrepancies']} subject discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Hospital audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
