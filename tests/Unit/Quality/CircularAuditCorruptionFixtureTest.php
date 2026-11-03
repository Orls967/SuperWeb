<?php

declare(strict_types=1);

namespace Tests\Unit\Quality;

use App\Quality\Audit\Fixtures\CircularAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Domain\Models\Shipment;
use Tests\TestCase;

class CircularAuditCorruptionFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_circular_audit_corruption_fixture_corrupts_shipment(): void
    {
        $fixture = new CircularAuditCorruptionFixture;

        $this->assertSame('logi:circular-audit', $fixture->command());

        $trackingNumber = $fixture->corrupt();
        $this->assertSame('CORRUPT-REV-TRK-999', $trackingNumber);

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();
        $this->assertNotNull($shipment);
        $this->assertSame('invalid_short_hash', $shipment->reverse_chain_hash);
        $this->assertSame(999999, $shipment->net_weight_grams);
        $this->assertSame('PLANT-CORRUPT', $shipment->plant_id);
    }
}
