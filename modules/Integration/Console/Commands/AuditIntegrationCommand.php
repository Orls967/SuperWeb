<?php

declare(strict_types=1);

namespace Modules\Integration\Console\Commands;

use Illuminate\Console\Command;
use Modules\Integration\Application\Services\IntegrationService;

class AuditIntegrationCommand extends Command
{
    protected $signature = 'api:audit';

    protected $description = 'Audit API integrations, EDI transmissions, and webhook HMAC signature validity';

    public function handle(IntegrationService $service): int
    {
        $this->info('Starting B2B API & EDI Integration Audit...');

        $res = $service->auditIntegration();

        $this->line("Status: {$res['status']}");
        $this->line("Webhook Subscriptions: {$res['webhook_count']}");
        $this->line("Webhook Deliveries: {$res['delivery_count']}");
        $this->line("EDI Messages: {$res['edi_message_count']}");
        $this->line("API Clients: {$res['client_count']}");
        $this->line("Discrepancies: {$res['discrepancy_count']}");

        if ($res['discrepancy_count'] > 0) {
            $this->error('Integration audit FAILED: Signature or message discrepancy detected!');

            return self::FAILURE;
        }

        $this->info('API & EDI Integration audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
