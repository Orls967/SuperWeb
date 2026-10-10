<?php

declare(strict_types=1);

namespace Modules\Integration\Console\Commands;

use Illuminate\Console\Command;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Application\Services\PlatformEconomyService;

/**
 * `api:audit` — the single API audit (PROGRESS R0.6). It used to be declared by two
 * classes, so the Fase 55 integration audit silently never ran. Both checks now run:
 * webhook HMAC & EDI integrity (Fase 55/102.5) and platform economy billing,
 * embedded finance and white-label isolation (Fase 147). Any discrepancy fails.
 */
class AuditIntegrationCommand extends Command
{
    protected $signature = 'api:audit';

    protected $description = 'Audit API: tanda tangan HMAC webhook & EDI (Fase 55) + platform economy — billing API developer, embedded finance, isolasi white label (Fase 147)';

    public function handle(IntegrationService $integration, PlatformEconomyService $platformEconomy): int
    {
        $this->info('Starting B2B API & EDI Integration Audit...');

        $integrationResult = $integration->auditIntegration();

        $this->line("Status: {$integrationResult['status']}");
        $this->line("Webhook Subscriptions: {$integrationResult['webhook_count']}");
        $this->line("Webhook Deliveries: {$integrationResult['delivery_count']}");
        $this->line("EDI Messages: {$integrationResult['edi_message_count']}");
        $this->line("API Clients: {$integrationResult['client_count']}");
        $this->line("Discrepancies: {$integrationResult['discrepancy_count']}");

        if ($integrationResult['discrepancy_count'] > 0) {
            $this->error('Integration audit FAILED: Signature or message discrepancy detected!');
        } else {
            $this->info('API & EDI Integration audit PASSED with 0 discrepancy.');
        }

        $this->info('Auditing Platform Economy (Fase 147)...');

        $platformResult = $platformEconomy->audit();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $platformResult['status']],
                ['Total Developers', $platformResult['total_developers']],
                ['Total Marketplace Apps', $platformResult['total_apps']],
                ['Total White Label Tenants', $platformResult['total_tenants']],
                ['Total Embedded Tx', $platformResult['total_embedded_txs']],
                ['Discrepancy Count', $platformResult['discrepancy_count']],
            ]
        );

        $discrepancies = $integrationResult['discrepancy_count'] + $platformResult['discrepancy_count'];

        if ($discrepancies === 0) {
            $this->info('api:audit PASSED (0 discrepancies)');

            return self::SUCCESS;
        }

        $this->error("api:audit FAILED ({$discrepancies} discrepancies detected)");

        return self::FAILURE;
    }
}
