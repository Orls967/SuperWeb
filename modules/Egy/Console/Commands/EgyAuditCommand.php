<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Egy\Domain\Models\BessArbitrageRun;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\RenewableEnergyCertificate;
use Modules\Egy\Domain\Models\SmartMeter;

class EgyAuditCommand extends Command
{
    protected $signature = 'egy:audit';

    protected $description = 'Audit jaringan energi: beban trafo, telemetri smart meter, kontrak PPA, dan integritas REC';

    public function handle(): int
    {
        $this->info('Starting Smart Grid & Energy Operations Audit (egy:audit)...');

        $nodeCount = GridNode::count();
        $meterCount = SmartMeter::count();
        $readingCount = MeterReading::count();
        $ppaCount = PpaContract::count();
        $recCount = RenewableEnergyCertificate::count();
        $bessCount = BessArbitrageRun::count();

        $discrepancies = 0;

        // Check severe transformer overload
        $overloadedNodes = GridNode::whereColumn('current_load_mw', '>', 'peak_capacity_mw')->count();
        if ($overloadedNodes > 0) {
            $this->warn("Found {$overloadedNodes} transformers exceeding maximum design capacity.");
            $discrepancies += $overloadedNodes;
        }

        // Check negative energy in RECs
        $invalidRecs = RenewableEnergyCertificate::where('energy_mwh', '<=', 0)->count();
        if ($invalidRecs > 0) {
            $this->warn("Found {$invalidRecs} RECs with non-positive MWh value.");
            $discrepancies += $invalidRecs;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Grid Transformer Nodes', $nodeCount],
                ['Smart Meters', $meterCount],
                ['Telemetry Readings', $readingCount],
                ['PPA Contracts', $ppaCount],
                ['Renewable Energy Certificates', $recCount],
                ['BESS Arbitrage Runs', $bessCount],
                ['Integrity Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Energy audit FAILED with {$discrepancies} stability discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Energy audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
