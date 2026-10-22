<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\MobileOfflineSyncEngineService;
use Modules\Core\Domain\Models\SyncConflictLedger;
use Tests\TestCase;

class MobileOfflineSyncEngineTest extends TestCase
{
    use RefreshDatabase;

    private MobileOfflineSyncEngineService $syncEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncEngine = app(MobileOfflineSyncEngineService::class);
    }

    public function test_push_and_pull_mobile_sync_endpoints(): void
    {
        $deviceUuid = 'device-tablet-field-01';

        $pushResponse = $this->withHeaders([
            'X-Device-UUID' => $deviceUuid,
        ])->postJson('/api/v3/mobile/sync/push', [
            'mutations' => [
                [
                    'mutation_uuid' => 'mut-001',
                    'entity_type' => 'clinical_triage',
                    'entity_id' => 'tri-100',
                    'client_version' => 1,
                    'action' => 'CREATE',
                    'data' => ['severity' => 'RED'],
                ],
                [
                    'mutation_uuid' => 'mut-002',
                    'entity_type' => 'pharmacy_dispense',
                    'entity_id' => 'rx-400',
                    'client_version' => 1,
                    'action' => 'DISPENSE',
                    'data' => ['qty' => 20],
                ],
            ],
        ]);

        $pushResponse->assertStatus(200);
        $pushResponse->assertJson([
            'status' => 'success',
            'data' => [
                'device_uuid' => $deviceUuid,
                'processed_count' => 2,
                'conflicts_count' => 0,
                'synced_ids' => ['mut-001', 'mut-002'],
            ],
        ]);

        $pullResponse = $this->withHeaders([
            'X-Device-UUID' => $deviceUuid,
        ])->postJson('/api/v3/mobile/sync/pull');

        $pullResponse->assertStatus(200);
        $pullResponse->assertJsonStructure([
            'status',
            'device_uuid',
            'server_timestamp',
            'deltas',
            'server_clock',
        ]);
    }

    public function test_vector_clock_causality_detection(): void
    {
        // 1. Equal clocks
        $equal = $this->syncEngine->compareVectorClocks(
            ['node_a' => 1, 'node_b' => 2],
            ['node_a' => 1, 'node_b' => 2]
        );
        $this->assertSame('EQUAL', $equal);

        // 2. Greater client clock (client is strictly ahead)
        $greater = $this->syncEngine->compareVectorClocks(
            ['node_a' => 2, 'node_b' => 3],
            ['node_a' => 1, 'node_b' => 2]
        );
        $this->assertSame('GREATER', $greater);

        // 3. Lesser client clock (client is behind server)
        $less = $this->syncEngine->compareVectorClocks(
            ['node_a' => 1, 'node_b' => 1],
            ['node_a' => 2, 'node_b' => 2]
        );
        $this->assertSame('LESS', $less);

        // 4. Concurrent conflict (both diverged independently)
        $conflict = $this->syncEngine->compareVectorClocks(
            ['node_a' => 2, 'node_b' => 1],
            ['node_a' => 1, 'node_b' => 3]
        );
        $this->assertSame('CONFLICT', $conflict);
    }

    public function test_clinical_conflict_resolution_and_audit_ledger(): void
    {
        $conflict = $this->syncEngine->resolveClinicalConflict(
            deviceUuid: 'device-ambulance-09',
            entityType: 'patient_vitals',
            entityId: 'vit-7721',
            serverVersion: 3,
            clientVersion: 2
        );

        $this->assertInstanceOf(SyncConflictLedger::class, $conflict);
        $this->assertSame('SERVER_HEALTHCARE_AUTHORITY_PRIORITY', $conflict->resolution_strategy);
        $this->assertDatabaseHas('core_sync_conflict_ledgers', [
            'id' => $conflict->id,
            'device_uuid' => 'device-ambulance-09',
            'entity_type' => 'patient_vitals',
            'entity_id' => 'vit-7721',
        ]);
    }

    public function test_stock_conflict_resolution_and_audit_ledger(): void
    {
        $conflict = $this->syncEngine->resolveStockConflict(
            deviceUuid: 'device-pos-van-03',
            entityType: 'pharmacy_lot',
            entityId: 'lot-amox-88',
            serverVersion: 5,
            clientVersion: 4
        );

        $this->assertSame('LAST_WRITE_WINS_WITH_LEDGER_ADJUSTMENT', $conflict->resolution_strategy);
        $this->assertNotEmpty($conflict->audit_hash);
    }
}
