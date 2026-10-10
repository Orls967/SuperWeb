<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Integration\Application\Services\PlatformEconomyService;

/*
| PROGRESS R0.6: `api:audit` is one command that runs both the Fase 55 integration
| audit (webhook HMAC & EDI) and the Fase 147 platform economy audit. Before R0.6 two
| classes declared the same name and only the last one ever ran.
*/

uses(RefreshDatabase::class);

it('runs the integration and platform economy audits and passes on consistent data', function (): void {
    $integration = app(IntegrationService::class);
    $subscription = $integration->registerWebhook(partnerId: 'PTN-AUDIT', eventType: 'po.issued', targetUrl: 'https://vendor.example/hook');
    $integration->dispatchWebhook($subscription, 'EVT-1', ['po' => 1]);
    $platform = app(PlatformEconomyService::class);
    $developer = $platform->registerDeveloper('Audit Dev', 'audit@dev.test', 'PRO');
    $platform->processEmbeddedFinance($developer->developer_code, 'ESCROW', 500000.00, 2.0);

    $this->artisan('api:audit')
        ->expectsOutputToContain('API & EDI Integration audit PASSED with 0 discrepancy.')
        ->expectsOutputToContain('Auditing Platform Economy (Fase 147)...')
        ->expectsOutputToContain('api:audit PASSED (0 discrepancies)')
        ->assertExitCode(0);
});

it('fails when a webhook delivery payload no longer matches its HMAC signature', function (): void {
    $integration = app(IntegrationService::class);
    $subscription = $integration->registerWebhook(partnerId: 'PTN-AUDIT', eventType: 'po.issued', targetUrl: 'https://vendor.example/hook');
    $delivery = $integration->dispatchWebhook($subscription, 'EVT-1', ['po' => 1]);
    $delivery->forceFill(['payload' => json_encode(['po' => 2])])->saveQuietly();

    $this->artisan('api:audit')
        ->expectsOutputToContain('Integration audit FAILED: Signature or message discrepancy detected!')
        ->expectsOutputToContain('api:audit FAILED (1 discrepancies detected)')
        ->assertExitCode(1);
});

it('fails when an embedded finance transaction does not equal its fees', function (): void {
    $platform = app(PlatformEconomyService::class);
    $developer = $platform->registerDeveloper('Audit Dev', 'audit@dev.test', 'PRO');
    $platform->processEmbeddedFinance($developer->developer_code, 'ESCROW', 500000.00, 2.0);
    DB::table('pe_embedded_finance_txs')->update(['gross_amount' => 1]);

    $this->artisan('api:audit')
        ->expectsOutputToContain('API & EDI Integration audit PASSED with 0 discrepancy.')
        ->expectsOutputToContain('api:audit FAILED (1 discrepancies detected)')
        ->assertExitCode(1);
});
