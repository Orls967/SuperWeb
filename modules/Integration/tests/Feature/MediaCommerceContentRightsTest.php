<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\MediaCommerceContentRightsService;
use Tests\TestCase;

class MediaCommerceContentRightsTest extends TestCase
{
    use RefreshDatabase;

    protected MediaCommerceContentRightsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MediaCommerceContentRightsService::class);
    }

    public function test_expired_license_blocks_content_serving(): void
    {
        // 1. Register expired license (385.1 & 385.4)
        $this->service->registerLicense(
            licenseCode: 'LIC-STREAM-MOVIE-01',
            mediaAssetId: 'ASSET-CINEMA-BLOCKBUSTER',
            windowExpiresAt: now()->subDay() // Expired yesterday!
        );

        // 2. Serving expired content fails (385.3 & 385.4)
        try {
            $this->service->serveContent(
                servingCode: 'SRV-HOTEL-SCREEN-101',
                licenseCode: 'LIC-STREAM-MOVIE-01'
            );
            $this->fail('Expected exception for serving expired licensed media');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Rights license \'LIC-STREAM-MOVIE-01\' has expired', $e->getMessage());
        }

        // 3. Active license serves smoothly (385.4)
        $this->service->registerLicense(
            licenseCode: 'LIC-STREAM-MOVIE-02',
            mediaAssetId: 'ASSET-CINEMA-BLOCKBUSTER',
            windowExpiresAt: now()->addMonth() // Active!
        );

        $served = $this->service->serveContent(
            servingCode: 'SRV-HOTEL-SCREEN-102',
            licenseCode: 'LIC-STREAM-MOVIE-02'
        );
        $this->assertTrue((bool) $served->serving_permitted);
    }

    public function test_post_expiry_serving_incident_auto_stops_and_compensates_edge_case(): void
    {
        // Register active license that subsequently lapses
        $this->service->registerLicense(
            licenseCode: 'LIC-STREAM-SERIES-01',
            mediaAssetId: 'ASSET-EPISODE-1',
            windowExpiresAt: now()->addMinute()
        );

        // Incident resolved with auto-stop + compensation (385.5 Edge Case)
        $resolved = $this->service->resolvePostExpiryServingIncident(
            licenseCode: 'LIC-STREAM-SERIES-01',
            compensationAmountUsd: 1500.00
        );

        $this->assertTrue((bool) $resolved->distribution_stopped);
        $this->assertTrue((bool) $resolved->post_expiry_compensation_paid);
    }

    public function test_med_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerLicense('L-AUD', 'M1', now()->addDay());
        $this->service->serveContent('S-AUD', 'L-AUD');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unstopped expired license
        DB::table('global_media_content_rights_licenses')->insert([
            'license_code' => 'L-DEFECT-EXPIRED-UNSTOPPED',
            'media_asset_id' => 'M1',
            'window_expires_at' => now()->subDay(), // Expired!
            'distribution_stopped' => false, // Discrepancy!
            'post_expiry_compensation_paid' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
