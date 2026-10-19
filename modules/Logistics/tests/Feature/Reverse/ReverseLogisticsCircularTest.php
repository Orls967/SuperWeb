<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature\Reverse;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Application\Services\Reverse\ReverseLogisticsService;
use Modules\Logistics\Domain\Models\Reverse\ReverseItem;
use Modules\Logistics\Domain\Models\Reverse\ReverseOrder;
use Tests\TestCase;

class ReverseLogisticsCircularTest extends TestCase
{
    use RefreshDatabase;

    protected ReverseLogisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReverseLogisticsService::class);

        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_79_2_reverse_order_triggered_idempotently_from_event(): void
    {
        $items = [
            [
                'category' => 'COOKING_OIL_JELANTAH',
                'description' => 'Used vegetable cooking oil from Central Kitchen',
                'quantity' => 200.0,
                'uom' => 'LITER',
                'recycling_target' => 'BIODIESEL_REFINERY',
                'appraised_unit_price_idr' => 8_500, // 200 * 8,500 = 1,700,000 IDR
            ],
            [
                'category' => 'SCRAP_METAL',
                'description' => 'Machined metal shavings and offcuts',
                'quantity' => 150.0,
                'uom' => 'KG',
                'recycling_target' => 'SMELTER',
                'appraised_unit_price_idr' => 6_000, // 150 * 6,000 = 900,000 IDR
            ],
        ];

        // 1. Initial trigger
        $order1 = $this->service->triggerReverseOrderFromEvent(
            eventTriggerType: 'resto.waste_bulk',
            sourceReferenceId: 'BATCH-CK-20261010-01',
            pickupLocation: 'Central Kitchen CK-01',
            destinationFacility: 'Circular Hub Cikarang',
            itemsData: $items
        );

        $this->assertEquals('SCHEDULED', $order1->status);
        $this->assertEquals(2_600_000, $order1->total_appraised_value_idr);
        // Avoided emissions: (200 + 150) * 2.1 = 735.0 kg CO2e
        $this->assertEquals(735.0, (float) $order1->avoided_emissions_kg_co2);
        $this->assertNotNull($order1->manifest_number);
        $this->assertNotNull($order1->custody_hash);
        $this->assertCount(2, $order1->items);

        // 2. Duplicate trigger with same event and source -> returns identical instance
        $order2 = $this->service->triggerReverseOrderFromEvent(
            eventTriggerType: 'resto.waste_bulk',
            sourceReferenceId: 'BATCH-CK-20261010-01',
            pickupLocation: 'Central Kitchen CK-01',
            destinationFacility: 'Circular Hub Cikarang',
            itemsData: $items
        );

        $this->assertEquals($order1->id, $order2->id);
        $this->assertEquals(1, ReverseOrder::count());
    }

    public function test_79_3_circular_intake_posts_to_ledger_and_updates_status(): void
    {
        $items = [
            [
                'category' => 'USED_OIL',
                'description' => 'Used motor engine oil from AutoServe Bengkel',
                'quantity' => 500.0,
                'uom' => 'LITER',
                'recycling_target' => 'LUBRICANT_RECYCLE',
                'appraised_unit_price_idr' => 5_000, // 2,500,000 IDR
            ],
        ];

        $order = $this->service->triggerReverseOrderFromEvent(
            eventTriggerType: 'auto.oil_used',
            sourceReferenceId: 'AUTOSERVE-EV-BATCH-88',
            pickupLocation: 'AutoServe Hub Fatmawati',
            destinationFacility: 'Refinery Plant Marunda',
            itemsData: $items
        );

        $processed = $this->service->processCircularIntake($order);
        $this->assertEquals('PROCESSED', $processed->status);

        // Verify ledger balances
        $mfgScrap = LedgerAccount::where('code', 'mfg:scrap_inbound:IDR')->first();
        $lgxRev = LedgerAccount::where('code', 'lgx:circular_revenue:IDR')->first();
        $this->assertEquals('-2500000', (string) $mfgScrap->cached_balance);
        $this->assertEquals('2500000', (string) $lgxRev->cached_balance);
    }

    public function test_79_7_two_source_audit_passes_cleanly(): void
    {
        $items = [
            [
                'category' => 'COOKING_OIL_JELANTAH',
                'description' => 'Used cooking oil for biodiesel processing',
                'quantity' => 100.0,
                'uom' => 'LITER',
                'recycling_target' => 'BIODIESEL_REFINERY',
                'appraised_unit_price_idr' => 8_500,
            ],
        ];

        $order = $this->service->triggerReverseOrderFromEvent(
            eventTriggerType: 'resto.waste_bulk',
            sourceReferenceId: 'REF-CLEAN-AUDIT-01',
            pickupLocation: 'Banjarmasin Central Kitchen',
            destinationFacility: 'Biodiesel Plant Cikarang',
            itemsData: $items
        );

        $this->service->processCircularIntake($order);

        $audit = $this->service->auditCircular();

        $this->assertTrue($audit['passed']);
        $this->assertEmpty($audit['discrepancies']);
        $this->assertEquals(1, $audit['orders_checked']);
        $this->assertEquals(1, $audit['items_checked']);
    }

    public function test_79_7_audit_fails_when_data_is_corrupted(): void
    {
        $order = ReverseOrder::create([
            'reverse_code' => 'REV-CORRUPT-TEST-01',
            'event_trigger_type' => 'resto.waste_bulk',
            'source_reference_id' => 'CORRUPT-REF-01',
            'pickup_location' => 'Kitchen',
            'destination_facility' => 'Plant',
            'status' => 'SCHEDULED',
            'manifest_number' => 'MNF-CORRUPT-01',
            'custody_hash' => hash('sha256', 'corrupt'),
            'total_appraised_value_idr' => 500_000, // Injected corrupt total
            'avoided_emissions_kg_co2' => 210.0,
        ]);

        ReverseItem::create([
            'reverse_order_id' => $order->id,
            'item_category' => 'COOKING_OIL_JELANTAH',
            'description' => 'Cooking oil',
            'quantity_kg_or_units' => 100.0,
            'unit_of_measure' => 'LITER',
            'recycling_target' => 'BIODIESEL_REFINERY',
            'appraised_unit_price_idr' => 1000,
            'subtotal_idr' => 100_000, // Mismatch: 100,000 vs 500,000
        ]);

        $audit = $this->service->auditCircular();

        $this->assertFalse($audit['passed']);
        $this->assertNotEmpty($audit['discrepancies']);
        $this->assertStringContainsString('REV-CORRUPT-TEST-01', $audit['discrepancies'][0]);
    }
}
