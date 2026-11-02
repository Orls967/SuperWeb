<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mobility\Telematics\Application\Services\TelematicsIngestService;

class AuditTelematicsCommand extends Command
{
    protected $signature = 'tlm:audit';

    protected $description = 'Perform two-source verification of telematics devices, ticks, and DTC anomaly grounding';

    public function handle(TelematicsIngestService $service): int
    {
        $this->info('Starting Telematics & Fleet IoT Operations Audit...');

        $result = $service->auditTelematics();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Devices Checked', $result['device_count']],
                ['Telemetry Ticks Checked', $result['tick_count']],
                ['Baselines Checked', $result['baseline_count']],
                ['5-Minute Aggregates Checked', $result['aggregate_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Telematics audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Telematics audit PASSED cleanly.');

        return 0;
    }
}
