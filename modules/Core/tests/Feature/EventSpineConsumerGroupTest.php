<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Services\SpineConsumerRegistry;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Domain\Models\SimEventSpine;
use Tests\TestCase;

class EventSpineConsumerGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SpineConsumerRegistry::reset();
        parent::tearDown();
    }

    public function test_consumer_group_consumes_and_commits_offset(): void
    {
        /** @var EventSpineInterface $spine */
        $spine = app(EventSpineInterface::class);

        // Publish events
        $e1 = $spine->publish('lgx.shipment', 'ShipmentDelivered', ['shipment_id' => 'SHP-1'], 'IDEMP-1');
        $e2 = $spine->publish('lgx.shipment', 'ShipmentDelivered', ['shipment_id' => 'SHP-2'], 'IDEMP-2');
        $e3 = $spine->publish('auto.ev', 'SessionCompleted', ['session_id' => 'SES-1'], 'IDEMP-3');

        $handledEvents = [];
        SpineConsumerRegistry::register('logistics_worker', 'ShipmentDelivered', function (SimEventSpine $event) use (&$handledEvents) {
            $handledEvents[] = $event->payload['shipment_id'];
        });

        // Run worker on logistics topic
        $exit = Artisan::call('core:consume-spine', [
            'group' => 'logistics_worker',
            '--topic' => ['lgx.shipment.*'],
            '--once' => true,
        ]);

        $this->assertEquals(0, $exit);
        $this->assertEquals(['SHP-1', 'SHP-2'], $handledEvents);

        // Check committed offset
        $offset = $spine->getOffset('logistics_worker', 'lgx.shipment.*');
        $this->assertEquals($e2->id, $offset);

        // Re-run worker without new events: should process 0 events
        $outputBefore = Artisan::output();
        Artisan::call('core:consume-spine', [
            'group' => 'logistics_worker',
            '--topic' => ['lgx.shipment.*'],
            '--once' => true,
        ]);
        $outputAfter = Artisan::output();
        $this->assertStringContainsString('No new events found', $outputAfter);
    }

    public function test_replay_from_offset_n_is_idempotent(): void
    {
        /** @var EventSpineInterface $spine */
        $spine = app(EventSpineInterface::class);

        $e1 = $spine->publish('fintech.payment', 'PaymentSettled', ['tx_id' => 'TX-100'], 'IDEMP-PAY-1');
        $e2 = $spine->publish('fintech.payment', 'PaymentSettled', ['tx_id' => 'TX-200'], 'IDEMP-PAY-2');

        $invocationCount = 0;
        SpineConsumerRegistry::register('accounting_group', 'PaymentSettled', function (SimEventSpine $event) use (&$invocationCount) {
            $invocationCount++;
        });

        // First pass
        $exit1 = Artisan::call('core:consume-spine', [
            'group' => 'accounting_group',
            '--topic' => ['fintech.payment'],
            '--once' => true,
        ]);

        $this->assertEquals(0, $exit1);
        $this->assertEquals(2, $invocationCount);

        // Replay from offset 0
        $exit2 = Artisan::call('core:consume-spine', [
            'group' => 'accounting_group',
            '--topic' => ['fintech.payment'],
            '--from-offset' => 0,
            '--replay' => true,
            '--once' => true,
        ]);

        $this->assertEquals(0, $exit2);
        // Handler should not duplicate execution on idempotent replay
        $this->assertEquals(2, $invocationCount);
    }
}
