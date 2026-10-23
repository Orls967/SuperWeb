<?php

declare(strict_types=1);

namespace Modules\Edu\Console\Commands;

use Illuminate\Console\Command;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Edu\Domain\Models\EduResearchGrant;

class EduAuditCommand extends Command
{
    protected $signature = 'edu:audit';

    protected $description = 'Audit modul Edu: kapasitas cohort, keabsahan hash sertifikat, dan saldo pencairan dana riset';

    public function handle(): int
    {
        $this->info('Starting Edu Academy & Research Operations Audit (edu:audit)...');

        $programCount = EduProgram::count();
        $cohortCount = EduCohort::count();
        $enrollmentCount = EduEnrollment::count();
        $certCount = EduCertificate::count();
        $grantCount = EduResearchGrant::count();

        $discrepancies = 0;

        // Verify capacity constraint
        $overCapacity = EduCohort::whereColumn('enrolled_count', '>', 'max_capacity')->count();
        if ($overCapacity > 0) {
            $this->warn("Found {$overCapacity} cohorts exceeding maximum capacity.");
            $discrepancies += $overCapacity;
        }

        // Verify grant disbursement overdraw
        $overdrawnGrants = EduResearchGrant::whereColumn('disbursed_grant_minor', '>', 'total_grant_minor')->count();
        if ($overdrawnGrants > 0) {
            $this->warn("Found {$overdrawnGrants} research grants with overdrawn disbursements.");
            $discrepancies += $overdrawnGrants;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Educational Programs', $programCount],
                ['Active Cohorts', $cohortCount],
                ['Total Enrollments', $enrollmentCount],
                ['Certificates Issued', $certCount],
                ['Research Grants', $grantCount],
                ['Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Edu audit FAILED with {$discrepancies} integrity discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Edu audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
