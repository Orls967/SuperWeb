<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Events\EgyMeterReadV1;
use Modules\Esg\Application\Listeners\RecordScopeTwoElectricityEmissionsListener;
use Tests\TestCase;

class SpineEventAndEsgConsumerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_ingesting_meter_telemetry_fires_egy_meter_read_v1_event(): void
    {
        Event::fake([EgyMeterReadV1::class]);

        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-EVT-001',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'PROP-MALL-EVT',
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);
        $action->execute([
            'meter_number' => 'MTR-EVT-001',
            'reading_at' => CarbonImmutable::now()->toIso8601String(),
            'cumulative_wh' => 50000,
            'idempotency_key' => 'egy:telemetry:evt:01',
        ]);

        Event::assertDispatched(EgyMeterReadV1::class, function ($event) use ($meter) {
            return $event->meterId === $meter->id
                && $event->propertyId === 'PROP-MALL-EVT';
        });
    }

    public function test_esg_listener_records_scope_2_emissions_from_reading_event(): void
    {
        $listener = app(RecordScopeTwoElectricityEmissionsListener::class);

        $event = new EgyMeterReadV1(
            readingId: (string) Str::uuid(),
            meterId: 'MTR-EVT-002',
            propertyId: 'PROP-FAC-ESG',
            region: 'JAMALI',
            intervalWh: 100000, // 100 kWh
            readingAt: CarbonImmutable::now()->toIso8601String(),
        );

        $record = $listener->handle($event);

        // 100 kWh @ 800 gCO2/kWh = 80,000 g = 80 kg CO2
        expect($record->emissions_kg_co2)->toBe(80)
            ->and($record->scope)->toBe('SCOPE_2');
    }

    public function test_duplicate_event_with_same_reading_id_is_processed_only_once(): void
    {
        $listener = app(RecordScopeTwoElectricityEmissionsListener::class);
        $readingId = (string) Str::uuid();

        $event = new EgyMeterReadV1(
            readingId: $readingId,
            meterId: 'MTR-EVT-003',
            propertyId: 'PROP-HOSP-ESG',
            region: 'JAMALI',
            intervalWh: 50000,
            readingAt: CarbonImmutable::now()->toIso8601String(),
        );

        $r1 = $listener->handle($event);
        $r2 = $listener->handle($event);

        expect($r2->id)->toBe($r1->id);
    }

    public function test_unknown_region_in_reading_payload_is_rejected(): void
    {
        $listener = app(RecordScopeTwoElectricityEmissionsListener::class);

        $event = new EgyMeterReadV1(
            readingId: (string) Str::uuid(),
            meterId: 'MTR-EVT-004',
            propertyId: 'PROP-FAC-UNKNOWN',
            region: 'UNKNOWN_GRID_REGION',
            intervalWh: 50000,
            readingAt: CarbonImmutable::now()->toIso8601String(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown grid emission region');

        $listener->handle($event);
    }

    public function test_emission_factor_is_integer_grams_co2_per_kwh(): void
    {
        $listener = app(RecordScopeTwoElectricityEmissionsListener::class);
        $factor = $listener->getGridEmissionFactor('JAMALI');

        expect(is_int($factor))->toBeTrue()
            ->and($factor)->toBe(800); // 800 gCO2/kWh
    }

    public function test_event_payload_contains_factual_register_and_delta(): void
    {
        $event = new EgyMeterReadV1(
            readingId: 'READ-001',
            meterId: 'MTR-001',
            propertyId: 'PROP-001',
            region: 'JAMALI',
            intervalWh: 25000,
            readingAt: '2026-10-01T12:00:00Z',
            cumulativeWh: 125000,
        );

        $payload = $event->toPayload();

        expect($payload['interval_wh'])->toBe(25000)
            ->and($payload['cumulative_wh'])->toBe(125000)
            ->and($payload['reading_at'])->toBe('2026-10-01T12:00:00Z');
    }
}
