<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseDataMigrationRetirementService;
use Tests\TestCase;

class EnterpriseDataMigrationRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseDataMigrationRetirementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseDataMigrationRetirementService::class);
    }

    public function test_data_migration_and_legacy_retirement_flow(): void
    {
        // 473.1 Inventory migration
        $mig = $this->service->inventoryMigration('MIG-GL-LEGACY', 'ORACLE_11G', 'SAP_S4HANA', 120000);
        $this->assertEquals('inventoried', $mig->status);

        // 473.2 Pre-migration cleansing certified
        $this->service->certifyCleansing('MIG-GL-LEGACY');

        // 473.4 Execute migration with exact count reconciliation
        $executed = $this->service->executeMigration('MIG-GL-LEGACY', 120000);
        $this->assertEquals('migrated', $executed->status);
        $this->assertTrue((bool) $executed->source_target_hash_reconciled);

        // 473.3 & 473.5 Decommission legacy system with consumer waiver and realize cost savings
        $this->service->registerLegacySystem('SYS-ORACLE-11G', 'Legacy Finance Oracle DB');
        $decom = $this->service->decommissionLegacySystem('SYS-ORACLE-11G', true, 1800000000.00);

        $this->assertEquals('decommissioned', $decom->status);
        $this->assertTrue((bool) $decom->data_archived_and_access_revoked);
        $this->assertEquals(1800000000.00, (float) $decom->annual_cost_saving_realized);

        // 473.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_uncleansed_migration_and_unconfirmed_retirement_blocked_edge_cases(): void
    {
        // 473.6 Risk: Uncleansed migration is blocked
        $this->service->inventoryMigration('MIG-DIRTY', 'ACCESS_MDB', 'POSTGRES', 500);

        try {
            $this->service->executeMigration('MIG-DIRTY', 500);
            $this->fail('Expected exception for uncleansed migration');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('acceptance criteria not met', $e->getMessage());
        }

        // 473.5 Edge case: Decommissioning without consumer sign-off/waiver is blocked
        $this->service->registerLegacySystem('SYS-CRITICAL', 'Active Mainframe');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Downstream consumers have not confirmed cutoff');

        $this->service->decommissionLegacySystem('SYS-CRITICAL', false, 500000000.00);
    }
}
