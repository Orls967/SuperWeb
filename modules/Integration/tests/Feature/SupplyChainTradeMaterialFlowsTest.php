<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SupplyChainTradeMaterialFlowsService;
use Tests\TestCase;

class SupplyChainTradeMaterialFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected SupplyChainTradeMaterialFlowsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplyChainTradeMaterialFlowsService::class);
    }

    public function test_transit_quality_failure_triggers_quarantine_edge_case(): void
    {
        // 1. Shipment failing quality inspection in-transit is quarantined and cannot deliver (381.2, 381.4, 381.5 Edge Case)
        try {
            $this->service->inspectTransitShipment(
                shipmentCode: 'SHP-NICKEL-ORE-001',
                lotIdentifier: 'LOT-NICKEL-GRADE-A1',
                qualityPassed: false // Failed inspection!
            );
            $this->fail('Expected exception for failed transit quality inspection');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('failed quality inspection and is placed under quarantine trade hold', $e->getMessage());
        }

        // Verify quarantine state in DB
        $quarantined = DB::table('global_supply_chain_shipment_tracks')->where('shipment_code', 'SHP-NICKEL-ORE-001')->first();
        $this->assertNotNull($quarantined);
        $this->assertTrue((bool) $quarantined->quarantine_hold_active);
        $this->assertFalse((bool) $quarantined->delivery_to_consignee_permitted);

        // 2. High-quality shipment proceeds to consignee (381.2 & 381.4)
        $passed = $this->service->inspectTransitShipment(
            shipmentCode: 'SHP-NICKEL-ORE-002',
            lotIdentifier: 'LOT-NICKEL-GRADE-A2',
            qualityPassed: true
        );
        $this->assertFalse((bool) $passed->quarantine_hold_active);
        $this->assertTrue((bool) $passed->delivery_to_consignee_permitted);
    }

    public function test_reverse_flow_pickup_authorization_risk(): void
    {
        // 1. Pickup without manifest fails (381.3, 381.4, 381.6 Risk)
        try {
            $this->service->authorizeReverseFlowPickup(
                reverseManifestCode: 'REV-MNF-001',
                lotIdentifier: 'LOT-BATTERY-CELL-RECYCLE-01',
                hasVerifiedContract: true,
                hasPickupManifest: false // Missing manifest!
            );
            $this->fail('Expected exception for reverse pickup without manifest');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Mandatory contract and pickup manifest required before pickup authorization', $e->getMessage());
        }

        // 2. Pickup with verified contract & manifest succeeds (381.3 & 381.4)
        $authorized = $this->service->authorizeReverseFlowPickup(
            reverseManifestCode: 'REV-MNF-002',
            lotIdentifier: 'LOT-BATTERY-CELL-RECYCLE-01',
            hasVerifiedContract: true,
            hasPickupManifest: true
        );
        $this->assertTrue((bool) $authorized->pickup_authorized);
    }

    public function test_trade_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->inspectTransitShipment('S-AUD', 'L-AUD', true);
        $this->service->authorizeReverseFlowPickup('R-AUD', 'L-AUD', true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: failed shipment permitted to deliver
        DB::table('global_supply_chain_shipment_tracks')->insert([
            'shipment_code' => 'S-DEFECT-DELIVERED',
            'lot_identifier' => 'L-AUD',
            'transit_quality_passed' => false, // Discrepancy!
            'quarantine_hold_active' => false,
            'delivery_to_consignee_permitted' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
