<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyGrid;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\Posting;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Tests\TestCase;

class EgyGridSmartMeteringBillingE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_smart_metering_ingest_tou_billing_posting_and_idempotency(): void
    {
        $this->seed();
        (new \Modules\Egy\database\seeders\EgyRbacSeeder())->run();

        $user = User::factory()->create();
        $user->assignRole('energy_billing');

        $meter = SmartMeter::create([
            'meter_code' => 'MTR-EL-001',
            'utility_type' => 'electricity',
            'property_id' => 'PROP-101',
            'node_id' => 'NODE-EAST-01',
            'customer_party_id' => 'CUST-001',
            'is_active' => true,
        ]);

        $service = app(EnergyGridAndMeteringService::class);

        // 1. Ingest 15-minute telemetry readings
        $reading1 = $service->ingestReading(
            meterId: $meter->id,
            intervalStart: '2026-11-06 00:00:00',
            intervalEnd: '2026-11-06 00:15:00',
            consumptionKwh: 30,
            productionKwh: 0,
            peakKw: 25,
            idempotencyKey: 'INGEST-MTR-1-20261106-0000'
        );

        $this->assertDatabaseHas('egy_meter_readings', [
            'meter_id' => $meter->id,
            'consumption_kwh' => 30,
        ]);

        // 2. Idempotency of duplicate reading with same key or interval
        $readingDup = $service->ingestReading(
            meterId: $meter->id,
            intervalStart: '2026-11-06 00:00:00',
            intervalEnd: '2026-11-06 00:15:00',
            consumptionKwh: 30,
            productionKwh: 0,
            peakKw: 25,
            idempotencyKey: 'INGEST-MTR-1-20261106-0000'
        );
        $this->assertEquals($reading1->id, $readingDup->id);
        $this->assertEquals(1, MeterReading::where('meter_id', $meter->id)->count());

        // Ingest remaining TOU windows
        $service->ingestReading(
            meterId: $meter->id,
            intervalStart: '2026-11-06 00:15:00',
            intervalEnd: '2026-11-06 00:30:00',
            consumptionKwh: 90,
            productionKwh: 0,
            peakKw: 20,
            idempotencyKey: 'INGEST-MTR-1-20261106-0015'
        ); // Total off-peak = 120 kWh

        $service->ingestReading(
            meterId: $meter->id,
            intervalStart: '2026-11-06 08:00:00',
            intervalEnd: '2026-11-06 08:15:00',
            consumptionKwh: 200,
            productionKwh: 0,
            peakKw: 28,
            idempotencyKey: 'INGEST-MTR-1-20261106-0800'
        ); // Shoulder = 200 kWh

        $service->ingestReading(
            meterId: $meter->id,
            intervalStart: '2026-11-06 18:00:00',
            intervalEnd: '2026-11-06 18:15:00',
            consumptionKwh: 80,
            productionKwh: 0,
            peakKw: 30,
            idempotencyKey: 'INGEST-MTR-1-20261106-1800'
        ); // Peak = 80 kWh, max peakKw = 30

        // 3. Generate TOU Bill for Property PROP-101
        $invoice = $service->generateBilling(
            propertyId: 'PROP-101',
            period: '2026-11',
            idempotencyKey: 'BILL-PROP-101-202611'
        );

        // Vector check: 568.000 energy + 1.050.000 demand = 1.618.000
        $this->assertEquals(1618000, $invoice->total_amount_idr);
        $this->assertEquals('issued', $invoice->status);

        // 4. Verify Ledger Posting exact values
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'egy:grid_electricity_revenue',
        ]);

        $arAccount = LedgerAccount::where('code', 'ar:egy:customer:CUST-001:IDR')->first();
        $revAccount = LedgerAccount::where('code', 'egy:grid_electricity_revenue:IDR')->first();

        $this->assertNotNull($arAccount);
        $this->assertNotNull($revAccount);

        // 5. Idempotent bill generation with same key
        $invoiceDup = $service->generateBilling(
            propertyId: 'PROP-101',
            period: '2026-11',
            idempotencyKey: 'BILL-PROP-101-202611'
        );
        $this->assertEquals($invoice->id, $invoiceDup->id);
    }
}
