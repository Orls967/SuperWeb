<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\UniversalEventSpineOutboxService;
use Modules\Core\Domain\Models\SimEventSpine;
use Tests\TestCase;

class UniversalEventSpineOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_and_consume_event_spine_idempotently(): void
    {
        $service = app(UniversalEventSpineOutboxService::class);
        $key = 'evt:min:shipped:2026-10-22-001';

        $event1 = $service->publishEvent('mining', 'min.ore_shipped', ['tonnes' => 1250, 'site_code' => 'PIT-A'], $key);
        $event2 = $service->publishEvent('mining', 'min.ore_shipped', ['tonnes' => 1250, 'site_code' => 'PIT-A'], $key);

        $this->assertInstanceOf(SimEventSpine::class, $event1);
        $this->assertSame($event1->id, $event2->id);

        $consumedCount = 0;
        $handler = function (array $payload) use (&$consumedCount): string {
            $consumedCount++;

            return 'ACK_'.$payload['tonnes'];
        };

        // First consume
        $res1 = $service->consumeEvent($event1->id, 'logistics_consumer_group', $handler);
        $this->assertSame('PROCESSED', $res1['status']);
        $this->assertSame('ACK_1250', $res1['result']);
        $this->assertSame(1, $consumedCount);

        // Replay consume (same consumer group)
        $res2 = $service->consumeEvent($event1->id, 'logistics_consumer_group', $handler);
        $this->assertSame('ALREADY_PROCESSED', $res2['status']);
        $this->assertSame(1, $consumedCount);
    }
}
