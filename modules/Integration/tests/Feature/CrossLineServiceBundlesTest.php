<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CrossLineServiceBundlesService;
use Tests\TestCase;

class CrossLineServiceBundlesTest extends TestCase
{
    use RefreshDatabase;

    protected CrossLineServiceBundlesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrossLineServiceBundlesService::class);
    }

    public function test_vendor_settlements_sum_to_customer_payment(): void
    {
        // 1. Unbalanced shares fail (390.2 & 390.4)
        try {
            $this->service->bookBundle(
                bundleBookingCode: 'BND-BALI-VACATION-01',
                totalPriceUsd: 1200.00,
                hotelShareUsd: 700.00,
                fleetShareUsd: 400.00 // Sum = 1100 != 1200!
            );
            $this->fail('Expected exception for unbalanced bundle settlement shares');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Component shares ($1100) do not sum to customer package price ($1200)', $e->getMessage());
        }

        // 2. Balanced shares succeed (390.4)
        $bundle = $this->service->bookBundle(
            bundleBookingCode: 'BND-BALI-VACATION-02',
            totalPriceUsd: 1200.00,
            hotelShareUsd: 700.00,
            fleetShareUsd: 500.00 // Sum = 1200 == 1200!
        );
        $this->assertEquals(1200.00, $bundle->total_package_price_usd);
        $this->assertTrue((bool) $bundle->settlement_sums_balanced);
    }

    public function test_single_component_cancellation_prorates_refund_edge_case(): void
    {
        // Book valid bundle
        $this->service->bookBundle(
            bundleBookingCode: 'BND-JAKARTA-EXPO-01',
            totalPriceUsd: 800.00,
            hotelShareUsd: 500.00,
            fleetShareUsd: 300.00
        );

        // Fleet component cancelled -> pro-rata refund with customer notice, not complete silent cancellation (390.4 & 390.5 Edge Case)
        $updated = $this->service->handlePartialCancellation('BND-JAKARTA-EXPO-01', 'FLEET');

        $this->assertTrue((bool) $updated->partial_fulfillment_refunded);
        $this->assertEquals(300.00, (float) $updated->refund_amount_usd);
    }

    public function test_bundle_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->bookBundle('B-AUD', 100.0, 60.0, 40.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unbalanced bundle
        DB::table('global_cross_line_service_bundles')->insert([
            'bundle_booking_code' => 'B-DEFECT-UNBALANCED',
            'total_package_price_usd' => 500.00,
            'vendor_hotel_share_usd' => 200.00,
            'vendor_fleet_share_usd' => 100.00,
            'settlement_sums_balanced' => false, // Discrepancy!
            'partial_fulfillment_refunded' => false,
            'refund_amount_usd' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
