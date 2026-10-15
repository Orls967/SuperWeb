<?php

declare(strict_types=1);

namespace Modules\Hcm\Console\Commands;

use App\Security\BlindIndex;
use Illuminate\Console\Command;
use Modules\Hcm\Domain\Models\Employee;

class EncryptLegacyEmployeeNikCommand extends Command
{
    protected $signature = 'hcm:encrypt-legacy {--dry-run : Run without updating database records}';

    protected $description = 'Idempotently backfill encrypted NIK and blind index for legacy employee records';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Starting HCM legacy NIK encryption and blind index backfill'.($dryRun ? ' (DRY-RUN)' : '').'...');

        $employees = Employee::all();
        $total = $employees->count();
        $updated = 0;

        foreach ($employees as $employee) {
            $nik = $employee->nik;
            $currentBidx = $employee->nik_bidx;

            if ($nik !== null && $nik !== '') {
                $computedBidx = BlindIndex::forNik($nik);

                if ($currentBidx !== $computedBidx) {
                    $updated++;
                    if (! $dryRun) {
                        $employee->nik_bidx = $computedBidx;
                        $employee->save();
                    }
                }
            }
        }

        $this->info("Completed. Total records: {$total}, Updated: {$updated}".($dryRun ? ' (dry-run, no changes saved)' : ''));

        return self::SUCCESS;
    }
}
