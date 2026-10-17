<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\EventSpineInterface;
use Tests\TestCase;

class CrossModuleSpineExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_module_consumers_are_invoked_on_spine_events(): void
    {
        /** @var EventSpineInterface $spine */
        $spine = app(EventSpineInterface::class);

        Log::spy();

        // Publish cross-module events
        $spine->publish('store.order', 'store.order.paid.v1', [
            'order_id' => 'ORD-9900',
            'customer_id' => 'CUST-01',
            'amount_idr' => 1500000,
            'items' => [],
        ], 'SPINE-ORD-9900');

        $spine->publish('auto.ev', 'auto.ev.session_completed.v1', [
            'session_id' => 'SES-EV-01',
            'vin' => 'VIN-EV-123',
            'kwh_consumed' => 45.5,
            'battery_soh_percent' => 98.2,
        ], 'SPINE-EV-123');

        // Consume via logistics_worker
        $exitLogistics = Artisan::call('core:consume-spine', [
            'group' => 'logistics_worker',
            '--topic' => ['store.order'],
            '--once' => true,
        ]);
        $this->assertSame(0, $exitLogistics);

        // Consume via pricing_worker
        $exitPricing = Artisan::call('core:consume-spine', [
            'group' => 'pricing_worker',
            '--topic' => ['store.order'],
            '--once' => true,
        ]);
        $this->assertSame(0, $exitPricing);

        // Consume via esg_worker
        $exitEsg = Artisan::call('core:consume-spine', [
            'group' => 'esg_worker',
            '--topic' => ['auto.ev'],
            '--once' => true,
        ]);
        $this->assertSame(0, $exitEsg);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message) => str_contains($message, 'CrossModuleSpine: Logistics preparing shipment dispatch for Order ORD-9900'));

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message) => str_contains($message, 'CrossModuleSpine: Pricing resolving price lock for Order ORD-9900'));

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message) => str_contains($message, 'CrossModuleSpine: ESG calculating carbon offset for EV VIN VIN-EV-123: 45.5 kWh consumed'));
    }
}
