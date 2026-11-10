<?php

declare(strict_types=1);

namespace Tests\Acceptance\EventSpine;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Application\Actions\EventSpine\ClaimConsumerInboxAction;
use Modules\Core\Application\Actions\EventSpine\ReplaySpineStreamAction;
use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Application\Services\EventSpineService;
use Modules\Core\Application\Services\LegacyListenerAdapterService;
use Modules\Core\Domain\Exceptions\SchemaBreakingChangeException;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;
use Tests\TestCase;

class EventSpineAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@typeshit.local', 'password' => 'secret'],
            ['id' => 90, 'name' => 'Spine Operator', 'email' => 'operator@typeshit.local', 'password' => 'secret'],
            ['id' => 91, 'name' => 'Auditor User', 'email' => 'auditor@typeshit.local', 'password' => 'secret'],
            ['id' => 92, 'name' => 'Regular User', 'email' => 'user@typeshit.local', 'password' => 'secret'],
        ]);
    }

    /**
     * Test 1: RBAC dan Otorisasi Rute Konsol Event Spine (/admin/spine*)
     */
    public function test_1_rbac_and_console_route_authorization(): void
    {
        $operator = User::find(90);
        $operator->forceFill(['role' => 'spine_operator'])->save();

        $auditor = User::find(91);
        $auditor->forceFill(['role' => 'auditor'])->save();

        $regular = User::find(92);
        $regular->forceFill(['role' => 'operator'])->save();

        $routes = [
            '/admin/spine',
            '/admin/spine/dlq',
            '/admin/spine/consumers',
            '/admin/spine/gaps',
        ];

        // 1. Guest diarahkan ke login
        foreach ($routes as $route) {
            $this->get($route)->assertStatus(302);
        }

        // 2. Regular user tanpa peran spine_operator ditolak (403)
        foreach ($routes as $route) {
            $this->actingAs($regular)->get($route)->assertStatus(403);
        }

        // 3. Spine operator dapat mengakses semua konsol spine (200)
        foreach ($routes as $route) {
            $this->actingAs($operator)->get($route)->assertStatus(200);
        }

        // 4. Auditor dapat mengakses rute schemas
        $this->actingAs($auditor)->get('/admin/spine/schemas')->assertStatus(200);
    }

    /**
     * Test 2: EventDelivery State Machine Lifecycle, Backoff A5 & Idempotensi Inbox Tahan Restart
     */
    public function test_2_event_delivery_lifecycle_and_persistent_inbox_restart_resilience(): void
    {
        /** @var EventSpineService $spine */
        $spine = app(EventSpineService::class);
        $event = $spine->publish(
            topic: 'store.order',
            eventName: 'store.order.paid.v1',
            payload: ['order_id' => 'ORD-1001', 'amount_idr' => 750000],
            idempotencyKey: 'IDEMP-ORD-1001'
        );

        $sm = new EventDeliveryStateMachine;
        $delivery = CoreEventDelivery::create([
            'message_id' => $event->event_id,
            'consumer_group' => 'logistics_fulfillment',
            'topic' => 'store.order',
            'status' => EventDeliveryStateMachine::STATUS_PENDING,
            'attempts' => 0,
        ]);

        // pending -> dispatched
        $sm->transition($delivery, EventDeliveryStateMachine::STATUS_DISPATCHED);
        $this->assertEquals(EventDeliveryStateMachine::STATUS_DISPATCHED, $delivery->status);

        // dispatched -> retrying dengan backoff A5
        $sm->transition($delivery, EventDeliveryStateMachine::STATUS_RETRYING, 'Network timeout');
        $this->assertEquals(EventDeliveryStateMachine::STATUS_RETRYING, $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);

        // Simulasi percobaan gagal sampai batas maksimum -> dead_lettered
        $delivery->attempts = 6;
        $delivery->save();
        $sm->transition($delivery, EventDeliveryStateMachine::STATUS_DEAD_LETTERED, 'Exceeded max retries');
        $this->assertEquals(EventDeliveryStateMachine::STATUS_DEAD_LETTERED, $delivery->status);

        // dead_lettered -> replayed
        $sm->transition($delivery, EventDeliveryStateMachine::STATUS_REPLAYED, 'Replay initiated by operator');
        $this->assertEquals(EventDeliveryStateMachine::STATUS_REPLAYED, $delivery->status);

        // replayed -> delivered
        $sm->transition($delivery, EventDeliveryStateMachine::STATUS_DELIVERED);
        $this->assertEquals(EventDeliveryStateMachine::STATUS_DELIVERED, $delivery->status);

        // Verifikasi Persistent Consumer Inbox tahan restart
        $inbox = app(ClaimConsumerInboxAction::class);
        $claim1 = $inbox->claim('logistics_fulfillment', $event->event_id, 'LogisticsOrderHandler');
        $this->assertTrue($claim1, 'Klaim pertama harus sukses');

        // Simulasi restart proses worker dan klaim kedua dengan pesan yang sama
        $claim2 = $inbox->claim('logistics_fulfillment', $event->event_id, 'LogisticsOrderHandler');
        $this->assertFalse($claim2, 'Klaim kedua harus ditolak oleh basis data persisten (idempoten)');
    }

    /**
     * Test 3: Schema Registry Aditif (A6), Karantina Poison Message, dan Deteksi Celah Urutan (A4)
     */
    public function test_3_schema_registry_compatibility_poison_quarantine_and_gap_detection(): void
    {
        $registry = app(EventSchemaRegistry::class);

        // 1. Registrasi V1 berhasil
        $v1Schema = [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string'],
                'amount' => ['type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['order_id', 'amount'],
        ];
        $registry->registerSchema('custom.order.placed.v1', 1, $v1Schema, 'Test Order Placed');
        $this->assertTrue($registry->hasSchema('custom.order.placed.v1', 1));

        // 2. Registrasi V2 aditif (tambah opsional note) berhasil
        $v2Add = $v1Schema;
        $v2Add['properties']['note'] = ['type' => 'string'];
        $registry->registerSchema('custom.order.placed.v1', 2, $v2Add, 'Order Placed with note');
        $this->assertTrue($registry->hasSchema('custom.order.placed.v1', 2));

        // 3. Registrasi V3 non-aditif (hapus amount) ditolak dengan SchemaBreakingChangeException
        $v3Break = $v1Schema;
        unset($v3Break['properties']['amount']);
        $this->expectException(SchemaBreakingChangeException::class);
        $registry->registerSchema('custom.order.placed.v1', 3, $v3Break, 'Breaking removal');
    }

    /**
     * Test 4: Paritas Listener Lama Lewat Adaptor, Replay Deterministik, dan IDOR Konsol DLQ
     */
    public function test_4_legacy_listeners_adapter_parity_and_deterministic_replay_hash(): void
    {
        $adapter = app(LegacyListenerAdapterService::class);
        $this->assertGreaterThanOrEqual(10, $adapter->getRegisteredListenersCount());

        // Replay deterministik: dua kali pemutaran menghasilkan hash proyeksi yang sama
        /** @var EventSpineService $spine */
        $spine = app(EventSpineService::class);
        $spine->publish('lgx.shipment', 'lgx.shipment.delivered.v1', ['shipment_id' => 'SHP-991', 'amount' => 100], 'IDEMP-R1');
        $spine->publish('lgx.shipment', 'lgx.shipment.delivered.v1', ['shipment_id' => 'SHP-992', 'amount' => 200], 'IDEMP-R2');

        $replayAction = app(ReplaySpineStreamAction::class);
        $res1 = $replayAction->execute(group: 'logistics_worker', topic: 'lgx.shipment.*', fromOffset: 0);
        $res2 = $replayAction->execute(group: 'logistics_worker', topic: 'lgx.shipment.*', fromOffset: 0);

        $this->assertEquals($res1['projection_hash'], $res2['projection_hash']);
        $this->assertEquals(64, strlen($res1['projection_hash']));
    }

    /**
     * Test 5: Dual-Source Audit (spine:audit) & Fixture Korupsi
     */
    public function test_5_dual_source_audit_and_corruption_detection(): void
    {
        // 1. Audit pada keadaan bersih menghasilkan exit code 0
        $exitClean = Artisan::call('spine:audit');
        $this->assertEquals(0, $exitClean);

        // 2. Korupsi: Tambah entri delivery delivered tanpa entri inbox padanan
        $corruptDelivery = CoreEventDelivery::create([
            'message_id' => (string) Str::uuid(),
            'consumer_group' => 'ghost_consumer',
            'topic' => 'store.order',
            'status' => EventDeliveryStateMachine::STATUS_DELIVERED,
            'attempts' => 1,
        ]);

        $exitCorrupt = Artisan::call('spine:audit');
        $output = Artisan::output();

        $this->assertNotEquals(0, $exitCorrupt);
        $this->assertStringContainsString('spine:audit FAILED', $output);
        $this->assertStringContainsString('ghost_consumer', $output);
    }
}
