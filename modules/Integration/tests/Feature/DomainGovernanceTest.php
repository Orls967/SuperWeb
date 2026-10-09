<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\DomainGovernanceService;
use Tests\TestCase;

/**
 * Fase 185 — 30-Lini Domain Model, Master Data & Event Contract Freeze Tests
 *
 * Covers:
 *  (a) canonical 30-line domain model registry with single authority freeze
 *  (b) event contract breaking change strictly blocked (additive-only permitted)
 *  (c) master data entity resolution with global UUID mapping
 *  (d) governance:audit = 0 discrepancy
 */
class DomainGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected DomainGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DomainGovernanceService::class);
    }

    /**
     * (a) Domain registry with single source of truth authority.
     */
    public function test_domain_model_registry_freeze(): void
    {
        $domain = $this->service->registerDomain('L01', 'Otomotif & Bengkel', 'AUTOSERVE', 'FIN_LEDGER_CORE', 'WHS_STOCK_CORE');

        $this->assertSame('L01', $domain->line_code);
        $this->assertSame('FIN_LEDGER_CORE', $domain->ledger_authority);
        $this->assertTrue((bool) $domain->is_frozen);
    }

    /**
     * (b) Event schema contract evolution blocks breaking changes.
     */
    public function test_event_schema_contract_breaking_change_blocked(): void
    {
        // 1. Publish initial version 1
        $v1Schema = [
            'order_id' => 'string',
            'amount' => 'number',
            'customer_uuid' => 'string',
        ];
        $this->service->publishEventContract('OrderPlaced', 'COMMERCE', $v1Schema, 1);

        // 2. Backward-compatible additive evolution (version 2: adds 'notes' field) -> SUCCESS
        $v2Schema = [
            'order_id' => 'string',
            'amount' => 'number',
            'customer_uuid' => 'string',
            'notes' => 'string',
        ];
        $contractV2 = $this->service->publishEventContract('OrderPlaced', 'COMMERCE', $v2Schema, 2);
        $this->assertSame(2, (int) $contractV2->schema_version);

        // 3. Breaking change: remove 'amount' field -> BLOCKED with Exception
        $breakingSchema = [
            'order_id' => 'string',
            'customer_uuid' => 'string',
            'notes' => 'string',
        ];
        try {
            $this->service->publishEventContract('OrderPlaced', 'COMMERCE', $breakingSchema, 3);
            $this->fail('Expected exception for breaking schema change.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Breaking change detected in OrderPlaced', $e->getMessage());
        }
    }

    /**
     * (c) Master data canonical entity resolution.
     */
    public function test_master_entity_mapping(): void
    {
        $entity = $this->service->mapMasterEntity('PARTY', 'HEALTHCARE', 'HOSPITAL-RS-SARDJITO', ['province' => 'DIY']);

        $this->assertNotNull($entity->global_entity_uuid);
        $this->assertSame('PARTY', $entity->entity_type);
        $this->assertSame('HOSPITAL-RS-SARDJITO', $entity->canonical_identifier);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_domain_governance_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
