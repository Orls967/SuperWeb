<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyGrid;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Tests\TestCase;

class EgyGridAuditAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_passes_on_clean_data_and_fails_on_corruption_fixture(): void
    {
        $this->seed();

        $service = app(EnergyGridAndMeteringService::class);

        $meter = SmartMeter::create([
            'meter_code' => 'MTR-AUDIT-01',
            'utility_type' => 'electricity',
            'property_id' => 'PROP-AUDIT',
            'node_id' => 'NODE-01',
            'customer_party_id' => 'CUST-AUDIT',
            'is_active' => true,
        ]);

        $service->ingestReading(
            meterId: $meter->id,
            intervalStart: '2026-11-06 00:00:00',
            intervalEnd: '2026-11-06 00:15:00',
            consumptionKwh: 100,
            productionKwh: 0,
            peakKw: 20,
            idempotencyKey: 'INGEST-AUDIT-1'
        );

        $service->generateBilling(
            propertyId: 'PROP-AUDIT',
            period: '2026-11',
            idempotencyKey: 'BILL-AUDIT-1'
        );

        // 1. Clean data audit -> exit 0
        $exitClean = Artisan::call('egy:audit');
        $this->assertEquals(0, $exitClean, 'Command egy:audit harus exit 0 pada data bersih');

        // 2. Corrupt invoice total amount (tamper database)
        $inv = UtilityConsolidatedInvoice::where('property_id', 'PROP-AUDIT')->first();
        $inv->update(['total_amount_idr' => $inv->total_amount_idr + 999999]);

        // 3. Corrupted data audit -> exit != 0
        $exitCorrupt = Artisan::call('egy:audit');
        $this->assertNotEquals(0, $exitCorrupt, 'Command egy:audit harus gagal (exit != 0) saat data rusak/dikorupsi');
    }
}
