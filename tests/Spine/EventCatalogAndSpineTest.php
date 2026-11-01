<?php

declare(strict_types=1);

namespace Tests\Spine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Core\Application\Services\CrossModuleSpineHandlers;
use Modules\Core\Application\Services\SimTickRegistry;
use Modules\Core\Application\Services\SpineConsumerRegistry;
use Modules\Core\Domain\Events\EventCatalog;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Domain\Models\SimRun;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Tests\TestCase;

#[Group('spine')]
class EventCatalogAndSpineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * C1: Real Handler Coverage for EventCatalog.
     * Evaluates which of the 16 catalog events have actual runtime consumers in CrossModuleSpineHandlers.
     */
    public function test_c1_event_catalog_handler_coverage_reveals_unhandled_events(): void
    {
        SpineConsumerRegistry::reset();
        CrossModuleSpineHandlers::registerAll();

        $ref = new ReflectionClass(SpineConsumerRegistry::class);
        $prop = $ref->getProperty('handlers');
        $registeredSubscriptions = $prop->getValue();

        $catalog = EventCatalog::all();
        $this->assertCount(16, $catalog);

        $handledEventNames = [];
        foreach ($registeredSubscriptions as $group => $events) {
            foreach (array_keys($events) as $eventName) {
                $handledEventNames[$eventName] = true;
            }
        }

        $unhandledEvents = [];
        foreach (array_keys($catalog) as $eventName) {
            if (! isset($handledEventNames[$eventName])) {
                $unhandledEvents[] = $eventName;
            }
        }

        // Exactly 8 events currently lack registered spine handlers
        $this->assertCount(8, $unhandledEvents, 'Expected 8 unhandled events in spine catalog');
        $this->assertContains('lgx.temperature.breached.v1', $unhandledEvents);
        $this->assertContains('auto.telematics.anomaly_detected.v1', $unhandledEvents);
        $this->assertContains('mall.footfall.recorded.v1', $unhandledEvents);
        $this->assertContains('prop.meter.read.v1', $unhandledEvents);
        $this->assertContains('egy.meter.read.v1', $unhandledEvents);
        $this->assertContains('epc.project.handed_over.v1', $unhandledEvents);
        $this->assertContains('esg.emission.recorded.v1', $unhandledEvents);
        $this->assertContains('gov.proposal.passed.v1', $unhandledEvents);
    }

    /**
     * C2: Consumer Group Offset Commitment and Idempotent Replay.
     */
    public function test_c2_consumer_group_offset_and_idempotent_replay(): void
    {
        SpineConsumerRegistry::reset();
        $processedCount = 0;

        SpineConsumerRegistry::register('audit_worker', 'store.order.paid.v1', function (SimEventSpine $event) use (&$processedCount) {
            $processedCount++;
        });

        // Publish 5 events
        for ($i = 1; $i <= 5; $i++) {
            SimEventSpine::create([
                'event_id' => (string) Str::uuid(),
                'event_name' => 'store.order.paid.v1',
                'producer_module' => 'Store',
                'topic' => 'store.order',
                'version' => 1,
                'idempotency_key' => "order:paid:{$i}",
                'payload' => ['order_id' => "ORD-{$i}"],
                'occurred_at' => now(),
            ]);
        }

        // Run consumer once
        Artisan::call('core:consume-spine', ['group' => 'audit_worker', '--once' => true]);
        $this->assertEquals(5, $processedCount);

        // Running consumer again without new events should process 0 additional events
        Artisan::call('core:consume-spine', ['group' => 'audit_worker', '--once' => true]);
        $this->assertEquals(5, $processedCount, 'Idempotent consumption: no additional events processed');

        // Replaying from offset 0 should be idempotent: handler does not duplicate execution
        Artisan::call('core:consume-spine', [
            'group' => 'audit_worker',
            '--from-offset' => 0,
            '--replay' => true,
            '--once' => true,
        ]);
        $this->assertEquals(5, $processedCount, 'Idempotent replay: handler does not duplicate execution');
    }

    /**
     * C4: Simulation Kernel 7 Tick Handlers and Deterministic Run.
     */
    public function test_c4_simulation_kernel_seven_tick_handlers_and_deterministic_hash(): void
    {
        $registry = app(SimTickRegistry::class);
        $handlers = $registry->all();

        $this->assertCount(7, $handlers, 'SimTickRegistry must contain exactly 7 tick handlers');
        $this->assertArrayHasKey('depresiasi', $handlers);
        $this->assertArrayHasKey('cicilan', $handlers);
        $this->assertArrayHasKey('tagihan', $handlers);
        $this->assertArrayHasKey('expiry_poin_voucher', $handlers);
        $this->assertArrayHasKey('mrp', $handlers);
        $this->assertArrayHasKey('d_and_d', $handlers);
        $this->assertArrayHasKey('royalti', $handlers);

        // Two identical 3-day runs produce identical hashes
        Artisan::call('sim:run', ['--days' => 3, '--seed' => 42, '--name' => 'run-a']);
        $hashA = SimRun::where('name', 'run-a')->first()->last_state_hash;

        Artisan::call('sim:run', ['--days' => 3, '--seed' => 42, '--name' => 'run-b']);
        $hashB = SimRun::where('name', 'run-b')->first()->last_state_hash;

        $this->assertNotEmpty($hashA);
        $this->assertEquals($hashA, $hashB, 'Identical seed and days must produce deterministic state hash');
    }
}
