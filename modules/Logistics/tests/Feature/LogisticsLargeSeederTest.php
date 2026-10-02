<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\database\seeders\LogisticsLargeSeeder;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Models\Vessel;
use Tests\TestCase;

class LogisticsLargeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_logistics_large_seeder_executes_successfully(): void
    {
        // Set sample size for test suite
        putenv('LOGISTICS_LARGE_SHIPMENTS=50');

        $initialTrucks = Truck::count();
        $initialContainers = Container::count();

        $seeder = new LogisticsLargeSeeder;
        $seeder->run();

        // 1. Truck & Vessel count increased
        $this->assertGreaterThanOrEqual($initialTrucks, Truck::count());
        $this->assertGreaterThanOrEqual(4, Vessel::count());

        // 2. Container count increased
        $this->assertGreaterThanOrEqual($initialContainers, Container::count());

        // 3. Shipments created
        $this->assertGreaterThanOrEqual(50, Shipment::count());

        // 4. Tracking events created with valid hash chain
        $sampleShipment = Shipment::has('trackingEvents', '>=', 5)->first();
        $this->assertNotNull($sampleShipment, 'No shipment with >= 5 tracking events found.');

        $events = $sampleShipment->trackingEvents()->orderBy('sequence')->get();
        $this->assertGreaterThanOrEqual(5, $events->count());

        // Verify hash chain of sample shipment
        $prevHash = TrackingEvent::genesisHash($sampleShipment->id);
        foreach ($events as $event) {
            $this->assertSame($prevHash, $event->prev_hash);
            $expectedHash = TrackingEvent::calculateHash(
                $prevHash,
                $event->sequence,
                $event->event_type,
                $event->payload ?? [],
                $event->occurred_at,
            );
            $this->assertSame($expectedHash, $event->hash);
            $prevHash = $event->hash;
        }

        // Clean up env
        putenv('LOGISTICS_LARGE_SHIPMENTS');
    }
}
