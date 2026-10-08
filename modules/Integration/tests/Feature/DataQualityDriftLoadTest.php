<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataQualityDriftLoadService;
use Tests\TestCase;

class DataQualityDriftLoadTest extends TestCase
{
    use RefreshDatabase;

    protected DataQualityDriftLoadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataQualityDriftLoadService::class);
    }

    public function test_bad_data_quarantine_pipeline(): void
    {
        // 1. Ingesting corrupt/anomalous data triggers automatic quarantine (397.3 & 397.4)
        try {
            $this->service->ingestDataRecord(
                recordCode: 'REC-IOT-TEMPERATURE-ANOMALY-01',
                dataValue: 9999.99,
                isCorruptOrAnomalous: true // Corrupted sensor reading!
            );
            $this->fail('Expected exception for corrupt data quarantine');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('detected as anomalous/corrupt and quarantined', $e->getMessage());
        }

        // Verify quarantined record
        $record = DB::table('global_stress_data_quality_records')->where('record_code', 'REC-IOT-TEMPERATURE-ANOMALY-01')->first();
        $this->assertNotNull($record);
        $this->assertTrue((bool) $record->quarantined);

        // 2. Normal clean data ingests smoothly (397.4)
        $clean = $this->service->ingestDataRecord(
            recordCode: 'REC-IOT-TEMPERATURE-NORMAL-02',
            dataValue: 24.50,
            isCorruptOrAnomalous: false
        );
        $this->assertFalse((bool) $clean->quarantined);
    }

    public function test_price_feed_drift_auto_freezes_trading_edge_case(): void
    {
        // Severe drift (>15% jump) triggers automatic freeze (397.2, 397.4, 397.5 Edge Case)
        $feed = $this->service->evaluatePriceFeedDrift(
            feedSymbol: 'NICKEL_LME_INDEX',
            baselinePrice: 16000.00,
            incomingPrice: 22000.00, // 37.5% drift!
            maxAllowedDriftPercent: 15.0
        );

        $this->assertTrue((bool) $feed->price_drift_detected);
        $this->assertTrue((bool) $feed->trading_frozen);

        // Acceptable normal fluctuation does not freeze (397.4)
        $stableFeed = $this->service->evaluatePriceFeedDrift(
            feedSymbol: 'COPPER_LME_INDEX',
            baselinePrice: 9000.00,
            incomingPrice: 9100.00, // 1.1% drift
            maxAllowedDriftPercent: 15.0
        );
        $this->assertFalse((bool) $stableFeed->price_drift_detected);
        $this->assertFalse((bool) $stableFeed->trading_frozen);
    }

    public function test_data_quality_drift_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->ingestDataRecord('R-AUD', 50.0, false);
        $this->service->evaluatePriceFeedDrift('SYM-AUD', 100.0, 102.0, 15.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: corrupt record unquarantined
        DB::table('global_stress_data_quality_records')->insert([
            'record_code' => 'R-DEFECT-UNQUARANTINED',
            'data_value' => -999.0,
            'is_corrupt_or_anomalous' => true,
            'quarantined' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
