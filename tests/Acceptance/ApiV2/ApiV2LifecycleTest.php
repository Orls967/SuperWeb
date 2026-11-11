<?php

declare(strict_types=1);

use App\Models\User;
use App\Quality\Audit\Fixtures\ApiAuditCorruptionFixture;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Application\Api\ApiClientLifecycleService;
use Modules\Core\Application\Api\AsyncZipExportService;
use Modules\Core\Application\Api\EdiProcessingService;
use Modules\Core\Application\Api\RetentionArchiveService;
use Modules\Core\Application\Api\StreamingBulkImportService;
use Modules\Core\Application\Api\WebhookDeliveryOrchestrator;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Application\Tenancy\TenantScopeRegistry;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Algorithms\Api\WebhookSignatureVerifier;
use Modules\Core\Domain\Exceptions\TenantContextMissingException;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreEdiTransition;
use Modules\Core\Domain\Models\CoreTenant;
use Modules\Core\Domain\Models\CoreWebhook;
use Modules\Core\Domain\StateMachines\EdiInterchangeStateMachine;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('test_55_1_access_and_sanctum_token_abilities', function (): void {
    $this->seed(DatabaseSeeder::class);

    $apiAdmin = User::factory()->create(['role' => 'api_admin']);
    $developer = User::factory()->create(['role' => 'developer']);
    $securityOfficer = User::factory()->create(['role' => 'security_officer']);
    $unauthorized = User::factory()->create(['role' => 'customer']);

    foreach ([
        '/admin/api/clients',
        '/admin/api/usage',
        '/admin/api/edi',
        '/admin/api/imports',
        '/admin/api/webhooks',
        '/admin/api/retention',
    ] as $route) {
        $this->actingAs($apiAdmin)->get($route)->assertOk();
        $this->actingAs($unauthorized)->get($route)->assertForbidden();
    }

    $this->actingAs($developer)->get('/admin/developer')->assertOk();
    $this->actingAs($apiAdmin)->get('/admin/developer')->assertOk();
    $this->actingAs($unauthorized)->get('/admin/developer')->assertForbidden();

    $this->actingAs($securityOfficer)->get('/admin/tenancy/coverage')->assertOk();
    $this->actingAs($unauthorized)->get('/admin/tenancy/coverage')->assertForbidden();

    // Sanctum token abilities on API v2
    $tenant = CoreTenant::firstOrFail();
    $tokenUser = User::factory()->create(['role' => 'api_admin']);
    Sanctum::actingAs($tokenUser, ['only:unrelated']);
    $this->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->getJson('/api/v2/purchase-orders')
        ->assertForbidden();

    Sanctum::actingAs($tokenUser, ['resource:read']);
    $this->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->getJson('/api/v2/purchase-orders')
        ->assertOk();
});

it('test_55_2_api_v2_envelope_cursor_rfc7807_idempotency_and_b1_rate_limit', function (): void {
    $this->seed(DatabaseSeeder::class);

    $mutableClock = new class(CarbonImmutable::createFromTimestampMs(1700000000000, 'UTC')) implements ClockInterface
    {
        public function __construct(public CarbonImmutable $current) {}

        public function now(): CarbonInterface
        {
            return $this->current;
        }
    };
    app()->instance(ClockInterface::class, $mutableClock);

    $tenant = CoreTenant::firstOrFail();
    $clientService = app(ApiClientLifecycleService::class);
    $created = $clientService->provisionClient(
        tenantId: $tenant->id,
        name: 'Bucket Test Client',
        legalEntityName: 'PT Bucket Test Tbk',
        tier: 'SILVER',
        abilities: ['resource:read', 'resource:action', 'edi:write'],
        capacityOverride: 5,
        rateOverride: 2,
    );

    /** @var CoreApiClient $client */
    $client = $created['client'];
    $token = $created['plain_key'];

    // Deprecation header on shipments (v1 parity) + undeclared sort RFC 7807
    $respShipments = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/shipments?limit=2');
    $respShipments->assertOk()
        ->assertHeader('Deprecation', 'true')
        ->assertJsonStructure(['data', 'meta' => ['next_cursor', 'limit']]);

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/shipments?sort=non_existent_column')
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);

    // Cursor stability while inserting a new record between page 1 and page 2
    $supId = DB::table('sup_suppliers')->value('id');
    for ($p = 1; $p <= 4; $p++) {
        PurchaseOrder::create([
            'number' => sprintf('PO-%04d', $p),
            'supplier_id' => $supId,
            'title' => "Initial PO {$p}",
            'kind' => 'standard',
            'status' => 'approved',
            'total_amount' => 50000 * $p,
        ]);
    }

    $poListPage1 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/purchase-orders?limit=2&sort=number');
    $poListPage1->assertOk();
    $firstIds = array_column($poListPage1->json('data'), 'id');
    $nextCursor = $poListPage1->json('meta.next_cursor');
    expect($nextCursor)->not->toBeNull();

    PurchaseOrder::create([
        'number' => 'PO-0000-NEW-EARLY',
        'supplier_id' => DB::table('sup_suppliers')->value('id'),
        'title' => 'Mid-Cursor Insert',
        'kind' => 'standard',
        'status' => 'approved',
        'total_amount' => 100000,
    ]);

    $poListPage2 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/purchase-orders?limit=2&sort=number&cursor='.urlencode($nextCursor));
    $poListPage2->assertOk();
    $secondIds = array_column($poListPage2->json('data'), 'id');
    expect(array_intersect($firstIds, $secondIds))->toBe([]);

    // Advance clock to refill bucket before idempotency tests
    $mutableClock->current = $mutableClock->current->addSeconds(10);

    // Idempotency enforcement (missing 422 -> replay same -> conflict 409)
    $po = PurchaseOrder::firstOrFail();
    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v2/purchase-orders/actions/acknowledge', ['po_id' => $po->id])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json');

    $firstAction = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => 'idem-55-ack-001',
    ])->postJson('/api/v2/purchase-orders/actions/acknowledge', ['po_id' => $po->id, 'note' => 'OK']);
    $firstAction->assertOk();

    $replayAction = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => 'idem-55-ack-001',
    ])->postJson('/api/v2/purchase-orders/actions/acknowledge', ['po_id' => $po->id, 'note' => 'OK']);
    $replayAction->assertOk()->assertHeader('X-Idempotent-Replay', 'true');
    expect($replayAction->json())->toBe($firstAction->json());

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => 'idem-55-ack-001',
    ])->postJson('/api/v2/purchase-orders/actions/acknowledge', ['po_id' => $po->id, 'note' => 'CHANGED'])
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');

    // Exact B1 Token Bucket HTTP 429 behavior: reset bucket full=5000 at t=0, rate=2
    CoreApiClient::where('id', $client->id)->update([
        'bucket_milli_tokens' => 5000,
        'bucket_last_ms' => $mutableClock->current->getTimestampMs(),
    ]);
    for ($i = 0; $i < 5; $i++) {
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v2/warehouses?limit=1')
            ->assertOk();
    }
    $rate429 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/warehouses?limit=1');
    $rate429->assertStatus(429)
        ->assertHeader('Retry-After', '1')
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('retry_after_ms', 500);

    $mutableClock->current = $mutableClock->current->addMilliseconds(500);
    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/warehouses?limit=1')
        ->assertOk();
    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v2/warehouses?limit=1')
        ->assertStatus(429)
        ->assertJsonPath('retry_after_ms', 500);
});

it('test_55_3_webhooks_hmac_rotation_event_delivery_dlq_cb_and_edi_sm', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tenant = CoreTenant::firstOrFail();
    $orchestrator = app(WebhookDeliveryOrchestrator::class);
    $webhook = CoreWebhook::create([
        'tenant_id' => $tenant->id,
        'code' => 'WH-TEST-55',
        'name' => 'Partner Webhook Receiver',
        'target_url' => 'https://partner.example/webhook',
        'event_topics' => ['order.status_changed.v1'],
        'secret_encrypted' => 'whsec_current_secret_key_55',
        'previous_secret_encrypted' => 'whsec_old_secret_key_55',
        'previous_secret_expires_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    Http::fake([
        'https://partner.example/*' => Http::sequence()
            ->push(['received' => true], 200)
            ->push(['err' => 1], 500)
            ->push(['err' => 2], 500)
            ->push(['err' => 3], 500)
            ->push(['err' => 4], 500)
            ->push(['err' => 5], 500)
            ->push(['recovered' => true], 200),
    ]);

    $delivered = $orchestrator->dispatch($webhook, 'order.status_changed.v1', 'EVT-WH-001', ['order' => 'ORD-1']);
    expect($delivered->status)->toBe('delivered')
        ->and($delivered->http_status)->toBe(200);

    Http::assertSent(function ($request) use ($webhook): bool {
        $sig = $request->header('X-Signature')[0] ?? '';
        $res = (new WebhookSignatureVerifier)->verify($sig, $request->body(), [$webhook->secret_encrypted], now()->getTimestamp(), 300);

        return str_contains($sig, ',') && $res['status'] === WebhookSignatureVerifier::STATUS_OK;
    });

    // 5 consecutive failures -> open circuit breaker + DLQ entry in W8-P3 CoreEventDelivery
    for ($i = 1; $i <= 5; $i++) {
        $orchestrator->dispatch($webhook->fresh(), 'order.status_changed.v1', "EVT-FAIL-{$i}", ['attempt' => $i]);
    }
    expect($webhook->fresh()->circuit_state)->toBe('open')
        ->and(DB::table('core_event_deliveries')->where('topic', 'order.status_changed.v1')->count())->toBeGreaterThanOrEqual(5);

    // EDI end-to-end across EDIFACT ORDERS, INVOIC (DRAFT without ledger), DESADV + rejection & immutable transition
    $ediService = app(EdiProcessingService::class);
    $ordersRaw = "UNB+UNOC:3+SENDER01:ZZ+RECEIVER01:ZZ+261108:1030+IC9001'UNH+1+ORDERS:D:96A:UN'BGM+220+PO99001+9'DTM+137:20261108:102'NAD+BY+BUYER?+01::9'LIN+1++ITEM-A:SRV'QTY+21:10'UNS+S'UNT+8+1'UNZ+1+IC9001'";
    $interchange = $ediService->processInbound($tenant->id, $ordersRaw, 'edi:orders:IC9001');
    expect($interchange->status)->toBe(EdiInterchangeStateMachine::STATUS_POSTED)
        ->and($interchange->contrl_payload)->toContain('UCI')
        ->and($interchange->handler_module)->toBe('Distribution');

    $ledgerBefore = DB::table('bank_ledger_entries')->count();
    $invoicRaw = "UNB+UNOC:3+SUP01:ZZ+RECEIVER01:ZZ+261108:1040+IC9002'UNH+1+INVOIC:D:96A:UN'BGM+380+INV99002+9'DTM+137:20261108:102'LIN+1++ITEM-A:SRV'QTY+47:5'UNS+S'UNT+7+1'UNZ+1+IC9002'";
    $invoic = $ediService->processInbound($tenant->id, $invoicRaw, 'edi:invoic:IC9002');
    expect($invoic->status)->toBe(EdiInterchangeStateMachine::STATUS_POSTED)
        ->and($invoic->handler_module)->toBe('Procurement')
        ->and(DB::table('bank_ledger_entries')->count())->toBe($ledgerBefore);

    $badEdi = $ediService->processInbound($tenant->id, str_replace('UNT+8+1', 'UNT+99+1', $ordersRaw), 'edi:orders:IC9003');
    expect($badEdi->status)->toBe(EdiInterchangeStateMachine::STATUS_REJECTED)
        ->and($badEdi->contrl_payload)->not->toBeEmpty();

    $tr = CoreEdiTransition::where('edi_interchange_id', $interchange->id)->firstOrFail();
    expect(fn () => $tr->update(['reason' => 'tamper']))->toThrow(DomainException::class);
    expect(fn () => $tr->delete())->toThrow(DomainException::class);
});

it('test_55_4_bulk_import_20k_resume_export_zip_and_tenant_isolation', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tenantA = CoreTenant::where('code', 'TENANT-A')->firstOrFail();
    $tenantB = CoreTenant::where('code', 'TENANT-B')->firstOrFail();

    // 20,000-row streaming CSV import with 120 invalid rows + interruption/resume + approval >10k
    $csvPath = storage_path('app/test_bulk_20k.csv');
    @mkdir(dirname($csvPath), 0777, true);
    $fh = fopen($csvPath, 'w');
    fputcsv($fh, ['sku', 'product_name', 'category', 'base_price_idr', 'min_order_qty', 'available_stock']);
    for ($r = 1; $r <= 20000; $r++) {
        $price = ($r <= 120) ? -500 : (10000 + $r);
        fputcsv($fh, ["SKU-55-{$r}", "Product {$r}", 'industrial', $price, 1, 100]);
    }
    fclose($fh);

    $importer = app(StreamingBulkImportService::class);
    $job = $importer->createAndValidate($tenantA->id, 'b2b_catalog', $csvPath);
    expect($job->total_rows)->toBe(20000)
        ->and($job->error_rows)->toBe(120)
        ->and($job->approval_id)->not->toBeNull();

    $approver = User::factory()->create(['role' => 'api_admin']);
    $importer->approveJob($job, $approver->id);

    // Process up to maxBatches=5 (2,500 rows) then resume via artisan command to finish all 19,880 valid rows
    $importer->executeBatches($job->fresh(), 5);
    expect($job->fresh()->processed_rows)->toBeLessThan(20000);

    $this->artisan('api:import', ['job' => $job->id, '--resume' => true])->assertExitCode(0);
    expect($job->fresh()->status)->toBe('completed')
        ->and(DB::table('b2b_wholesale_catalogs')->where('sku', 'like', 'SKU-55-%')->count())->toBe(19880);

    // 50,000-row async ZIP export + HMAC signed URL expiry (15m)
    $exporter = app(AsyncZipExportService::class);
    $exportJob = $exporter->exportChunkedZip($tenantA->id, 'b2b_catalog', 50000);
    expect(file_exists($exportJob->zip_path))->toBeTrue();
    $signedUrl = URL::temporarySignedRoute('admin.api.exports.download', now()->addMinutes(15), ['export' => $exportJob->id]);
    $this->actingAs($approver)->get($signedUrl)->assertOk();

    // Multi-tenant isolation across all registered models + fail-closed + audit bypass
    $scopeRegistry = app(TenantScopeRegistry::class);
    expect(count($scopeRegistry->registeredModels()))->toBeGreaterThanOrEqual(6);

    CoreApiClient::create([
        'tenant_id' => $tenantA->id,
        'code' => 'CLI-TENANT-A-TEST',
        'name' => 'Tenant A Client',
        'legal_entity_name' => 'PT Tenant A',
        'tier' => 'SILVER',
        'capacity' => 10,
        'refill_per_sec' => 2,
        'daily_quota' => 1000,
        'bucket_milli_tokens' => 10000,
        'bucket_last_ms' => 0,
        'is_active' => true,
    ]);
    CoreApiClient::create([
        'tenant_id' => $tenantB->id,
        'code' => 'CLI-TENANT-B-TEST',
        'name' => 'Tenant B Client',
        'legal_entity_name' => 'PT Tenant B',
        'tier' => 'SILVER',
        'capacity' => 10,
        'refill_per_sec' => 2,
        'daily_quota' => 1000,
        'bucket_milli_tokens' => 10000,
        'bucket_last_ms' => 0,
        'is_active' => true,
    ]);

    $scopeRegistry->withStrictEnforcement(function () use ($scopeRegistry, $tenantA): void {
        expect(fn () => app(RetentionArchiveService::class)->runBatchForCurrentTenant())
            ->toThrow(TenantContextMissingException::class);

        TenantContext::runWith($tenantA, function () {
            $foreignRows = DB::table('core_api_clients')->where('tenant_id', '!=', TenantContext::current()->tenantId)->count();
            $visible = CoreApiClient::all();
            expect($visible->every(fn ($c) => $c->tenant_id === TenantContext::current()->tenantId))->toBeTrue()
                ->and($foreignRows)->toBeGreaterThan(0);
        });

        $bypassResult = $scopeRegistry->withoutTenantScope('tenancy:audit_read', 'Penetration test check', fn () => CoreApiClient::count());
        expect($bypassResult)->toBeGreaterThanOrEqual(2)
            ->and(DB::table('core_tenant_scope_bypasses')->count())->toBeGreaterThanOrEqual(1);
    });
});

it('test_55_5_api_audit_clean_and_corruption_fixtures', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->artisan('api:audit')
        ->expectsOutputToContain('api:audit PASSED (0 discrepancies)')
        ->assertExitCode(0);

    $cov1 = $this->artisan('api:tenancy-coverage')->execute();
    expect($cov1)->toBe(0);

    $fixture = new ApiAuditCorruptionFixture;
    foreach ($fixture->allScenarios() as $scenario => $expectedText) {
        DB::beginTransaction();
        try {
            $fixture->corruptScenario($scenario);
            $this->artisan('api:audit')
                ->assertFailed()
                ->expectsOutputToContain($expectedText);
        } finally {
            DB::rollBack();
        }
    }
});
