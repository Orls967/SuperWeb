<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Domain\Events\EventCatalog;
use Modules\Core\Domain\Models\CoreEventSchema;
use Tests\TestCase;

class EventCatalogContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_catalog_events_follow_versioned_naming_and_have_producers_and_consumers(): void
    {
        $catalog = EventCatalog::all();

        $this->assertNotEmpty($catalog, 'Event catalog should not be empty.');
        $this->assertGreaterThanOrEqual(16, count($catalog), 'Catalog must contain minimum events from KONSEP §A4.2.');

        foreach ($catalog as $name => $definition) {
            $this->assertEquals($name, $definition['name']);
            EventCatalog::validateEventIntegrity($definition);

            $this->assertMatchesRegularExpression(
                '/^[a-z0-9]+\.[a-z0-9_]+\.[a-z0-9_]+\.v[0-9]+$/',
                $name,
                "Event name '{$name}' must match {domain}.{entitas}.{kejadian}.v{n} convention."
            );

            $this->assertNotEmpty($definition['producer'], "Event '{$name}' must have a valid producer module.");
            $this->assertNotEmpty($definition['consumers'], "Cross-module event '{$name}' must have consumers.");
            $this->assertGreaterThanOrEqual(1, count($definition['consumers']));
        }
    }

    public function test_catalog_validation_fails_when_cross_module_event_has_no_consumers(): void
    {
        $invalidDefinition = [
            'name' => 'store.order.cancelled.v1',
            'version' => 1,
            'producer' => 'Store',
            'consumers' => [], // Empty consumers!
            'purpose' => 'Test event without consumer',
            'payload_schema' => ['order_id' => 'string'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Event lintas modul 'store.order.cancelled.v1' dilarang tanpa konsumen nyata");

        EventCatalog::validateEventIntegrity($invalidDefinition);
    }

    public function test_catalog_validation_fails_when_event_name_is_malformed(): void
    {
        $invalidDefinition = [
            'name' => 'OrderCancelled', // Not versioned domain format
            'version' => 1,
            'producer' => 'Store',
            'consumers' => ['Logistics'],
            'purpose' => 'Malformed name',
            'payload_schema' => [],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Format nama event 'OrderCancelled' tidak valid");

        EventCatalog::validateEventIntegrity($invalidDefinition);
    }

    public function test_payload_structure_enforces_required_metadata_and_data(): void
    {
        $validPayload = [
            'event_id' => '018f3a2c-4e89-72c1-b0e9-b684cb6b83f0',
            'occurred_at' => '2026-10-17T03:00:00+00:00',
            'aggregate_type' => 'StoreOrder',
            'aggregate_id' => 'ORD-1001',
            'idempotency_key' => 'IDEMP-PAY-1001',
            'schema_version' => 1,
            'data' => [
                'order_id' => 'ORD-1001',
                'amount_idr' => 750000,
            ],
        ];

        $this->assertTrue(EventCatalog::validatePayloadStructure('store.order.paid.v1', $validPayload));

        // Missing aggregate_id
        $invalidPayload = $validPayload;
        unset($invalidPayload['aggregate_id']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Payload event 'store.order.paid.v1' kekurangan field wajib 'aggregate_id'");

        EventCatalog::validatePayloadStructure('store.order.paid.v1', $invalidPayload);
    }

    public function test_catalog_syncs_to_database_successfully(): void
    {
        $count = EventCatalog::syncToDatabase();
        $this->assertGreaterThanOrEqual(16, $count);

        $this->assertDatabaseHas('core_event_schemas', [
            'event_name' => 'store.order.paid.v1',
            'version' => 1,
            'producer_module' => 'Store',
        ]);

        $this->assertDatabaseHas('core_event_schemas', [
            'event_name' => 'lgx.shipment.delivered.v1',
            'producer_module' => 'Logistics',
        ]);

        $schema = CoreEventSchema::where('event_name', 'store.order.paid.v1')->first();
        $this->assertNotNull($schema);
        $this->assertContains('Logistics', $schema->consumer_modules);
        $this->assertContains('Manufacturing', $schema->consumer_modules);
        $this->assertContains('Pricing', $schema->consumer_modules);
    }
}
