<?php

declare(strict_types=1);

namespace Modules\Fleet\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AuditMobilityCommand extends Command
{
    protected $signature = 'mob:audit';

    protected $description = 'Perform consolidated two-source audit of Mobility ecosystem (Telematics, EV Charging, Fleet Leasing)';

    public function handle(): int
    {
        $this->info('Starting Consolidated Mobility Ecosystem Audit...');

        $exitTlm = Artisan::call('tlm:audit');
        $this->line(Artisan::output());

        $exitEv = Artisan::call('ev:audit');
        $this->line(Artisan::output());

        $exitFlt = Artisan::call('flt:audit');
        $this->line(Artisan::output());

        if ($exitTlm !== 0 || $exitEv !== 0 || $exitFlt !== 0) {
            $this->error('Consolidated Mobility Audit FAILED.');

            return 1;
        }

        $this->info('Consolidated Mobility Audit PASSED cleanly.');

        return 0;
    }
}
