<?php

declare(strict_types=1);

namespace Tests\Acceptance\ReverseLogistics;

use App\Models\User;
use App\Quality\Audit\Fixtures\CircularAuditCorruptionFixture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Esg\Contracts\EmissionBenefitRecorder;
use Modules\Logistics\Application\Actions\Reverse\AccrueCarrierCostAction;
use Modules\Logistics\Application\Actions\Reverse\CreateReverseShipmentAction;
use Modules\Logistics\Application\Actions\Reverse\ProcessPlantIntakeAction;
use Modules\Logistics\Application\Actions\Reverse\TransitionReverseShipmentAction;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidShipmentTransitionException;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Manufacturing\Contracts\RecycledMaterialReceiver;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\Plant;
use Modules\Manufacturing\Domain\Models\RecycledMaterialReceipt;
use Tests\TestCase;

class ReverseLogisticsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $hubOperator;
    protected User $plantReceiver;
    protected User $customerUser;
    protected Location $hubLocation;
    protected Location $plantLocation;
    protected Plant $plant;
    protected Material $material;
    protected Carrier $carrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LedgerAccountsSeeder::class);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->hubOperator = User::factory()->create(['role' => 'hub_operator']);
        $this->plantReceiver = User::factory()->create(['role' => 'plant_receiver']);
        $this->customerUser = User::factory()->create(['role' => 'customer']);

        $this->hubLocation = Location::create([
            'code' => 'HUB-TEST-01',
            'name' => 'Hub Reverse Cikarang',
            'type' => 'hub',
            'city' => 'Cikarang',
            'province' => 'Jawa Barat',
            'country' => 'ID',
            'lat_e6' => -6300000,
            'lng_e6' => 107100000,
            'is_active' => true,
        ]);

        $this->plantLocation = Location::create([
            'code' => 'PLANT-LOC-01',
            'name' => 'Pabrik Karawang',
            'type' => 'warehouse',
            'city' => 'Karawang',
            'province' => 'Jawa Barat',
            'country' => 'ID',
            'lat_e6' => -6320000,
            'lng_e6' => 107300000,
            'is_active' => true,
        ]);

        $this->plant = Plant::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'PLANT-KRW-01',
            'name' => 'Pabrik Daur Ulang Karawang',
            'nominal_capacity_per_day' => 20000,
            'is_active' => true,
        ]);

        $this->material = Material::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'PET_FLAKES',
            'name' => 'PET Flakes Bersih',
            'kind' => 'raw',
            'base_uom' => 'kg',
            'is_active' => true,
        ]);

        $this->carrier = Carrier::create([
            'code' => 'CARRIER-RL-01',
            'name' => 'PT Ekspedisi Sirkular',
            'mode' => 'road',
            'is_active' => true,
        ]);

        // Seed initial pricing quote
        DB::table('pric_material_prices')->insert([
            'material_code' => 'PET_FLAKES',
            'grade_code' => 'A',
            'price_per_kg_idr' => 12500,
            'effective_from' => '2026-01-01 00:00:00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_1_complete_reverse_shipment_lifecycle(): void
    {
        $createAction = app(CreateReverseShipmentAction::class);
        $transitionAction = app(TransitionReverseShipmentAction::class);
        $intakeAction = app(ProcessPlantIntakeAction::class);

        // 1. Create Reverse Shipment (Requested)
        $shipment = $createAction->execute([
            'reverse_reason' => 'material_sekunder',
            'source_party_id' => $this->adminUser->id,
            'material_code' => 'PET_FLAKES',
            'estimated_weight_grams' => 25000,
            'origin_location_id' => $this->hubLocation->id,
            'destination_facility_id' => $this->plantLocation->id,
        ], $this->adminUser);

        $shipment->update([
            'plant_id' => $this->plant->code,
            'grade_code' => 'A',
            'reverse_manifest_number' => 'MNF-REV-TEST-001',
            'reverse_chain_hash' => hash('sha256', 'test-chain-1'),
            'carrier_cost_idr' => 150000,
            'source_party_id' => $this->adminUser->id,
        ]);

        $this->assertEquals(ShipmentStatus::Requested, $shipment->status);
        $this->assertTrue($shipment->is_reverse);
        $this->assertNotEmpty($shipment->reverse_manifest_number);
        $this->assertEquals(64, strlen($shipment->reverse_chain_hash));

        // 2. Picked Up
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::PickedUp, $this->hubOperator);
        $this->assertEquals(ShipmentStatus::PickedUp, $shipment->status);

        // 3. At Hub
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::AtHub, $this->hubOperator);
        $this->assertEquals(ShipmentStatus::AtHub, $shipment->status);

        // 4. Delivered to Plant
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::DeliveredToPlant, $this->hubOperator);
        $this->assertEquals(ShipmentStatus::DeliveredToPlant, $shipment->status);

        // 5. Intake at Plant (Received)
        $result = $intakeAction->execute(
            shipment: $shipment,
            netWeightGrams: 25000,
            gradeCode: 'A',
            actor: $this->plantReceiver
        );

        $this->assertNotNull($result['lot_id']);
        $this->assertEquals(312500, $result['total_value_idr']); // 25kg * 12.500/kg

        $shipment->refresh();
        $this->assertEquals(ShipmentStatus::Received, $shipment->status);
        $this->assertNotNull($shipment->plant_receipt_id);
    }

    public function test_2_invalid_status_transition_is_rejected(): void
    {
        $this->expectException(InvalidShipmentTransitionException::class);

        $createAction = app(CreateReverseShipmentAction::class);
        $transitionAction = app(TransitionReverseShipmentAction::class);

        $shipment = $createAction->execute([
            'reverse_reason' => 'material_sekunder',
            'source_party_id' => $this->adminUser->id,
            'material_code' => 'PET_FLAKES',
            'estimated_weight_grams' => 10000,
            'origin_location_id' => $this->hubLocation->id,
            'destination_facility_id' => $this->plantLocation->id,
        ], $this->adminUser);

        // Langsung lompat dari Requested ke DeliveredToPlant (tidak valid)
        $transitionAction->execute($shipment, ShipmentStatus::DeliveredToPlant, $this->hubOperator);
    }

    public function test_3_idempotent_k1_recycled_receiver(): void
    {
        $receiver = app(RecycledMaterialReceiver::class);

        $shipment = Shipment::create([
            'tracking_number' => 'TRK-IDEM-001',
            'shipper_id' => $this->adminUser->id,
            'consignee_name' => 'Karawang Plant',
            'consignee_phone' => '08123456789',
            'consignee_address' => ['address' => 'Plant Karawang'],
            'origin_location_id' => $this->hubLocation->id,
            'destination_location_id' => $this->plantLocation->id,
            'status' => ShipmentStatus::DeliveredToPlant,
            'is_reverse' => true,
            'reverse_reason' => 'material_sekunder',
            'material_code' => 'PET_FLAKES',
            'grade_code' => 'A',
            'net_weight_grams' => 50000,
            'plant_id' => $this->plant->code,
        ]);

        $idemKey = 'IDEM-RECV-TEST-999';

        $id1 = $receiver->receive(
            reverseShipmentId: $shipment->id,
            materialCode: 'PET_FLAKES',
            gradeCode: 'A',
            quantityGrams: 50000,
            unitPricePerKgIdr: 12500,
            idempotencyKey: $idemKey
        );

        // Panggilan kedua dengan idempotency key yang sama
        $id2 = $receiver->receive(
            reverseShipmentId: $shipment->id,
            materialCode: 'PET_FLAKES',
            gradeCode: 'A',
            quantityGrams: 50000,
            unitPricePerKgIdr: 12500,
            idempotencyKey: $idemKey
        );

        $this->assertEquals($id1, $id2);
        $this->assertEquals(1, RecycledMaterialReceipt::where('idempotency_key', $idemKey)->count());
    }

    public function test_4_plant_intake_rejection_marks_shipment_rejected(): void
    {
        $createAction = app(CreateReverseShipmentAction::class);
        $transitionAction = app(TransitionReverseShipmentAction::class);

        $shipment = $createAction->execute([
            'reverse_reason' => 'material_sekunder',
            'source_party_id' => $this->adminUser->id,
            'material_code' => 'PET_FLAKES',
            'estimated_weight_grams' => 10000,
            'origin_location_id' => $this->hubLocation->id,
            'destination_facility_id' => $this->plantLocation->id,
        ], $this->adminUser);

        $shipment = $transitionAction->execute($shipment, ShipmentStatus::PickedUp, $this->hubOperator);
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::AtHub, $this->hubOperator);
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::DeliveredToPlant, $this->hubOperator);

        // Transisi ke Rejected
        $shipment = $transitionAction->execute(
            $shipment,
            ShipmentStatus::Rejected,
            $this->plantReceiver,
            note: 'Kontaminasi minyak dan pasir melebihi batas toleransi'
        );

        $this->assertEquals(ShipmentStatus::Rejected, $shipment->status);
    }

    public function test_5_missing_price_quote_throws_exception_on_intake(): void
    {
        $this->expectException(ValidationException::class);

        $intakeAction = app(ProcessPlantIntakeAction::class);

        $shipment = Shipment::create([
            'tracking_number' => 'TRK-NOPRICE-001',
            'shipper_id' => $this->adminUser->id,
            'consignee_name' => 'Plant',
            'consignee_phone' => '08123',
            'consignee_address' => ['city' => 'JKT'],
            'origin_location_id' => $this->hubLocation->id,
            'destination_location_id' => $this->plantLocation->id,
            'status' => ShipmentStatus::DeliveredToPlant,
            'is_reverse' => true,
            'reverse_reason' => 'material_sekunder',
            'material_code' => 'UNKNOWN_CODE',
            'grade_code' => 'A',
            'net_weight_grams' => 10000,
            'plant_id' => $this->plant->code,
        ]);

        $intakeAction->execute(
            shipment: $shipment,
            netWeightGrams: 10000,
            gradeCode: 'A',
            actor: $this->plantReceiver
        );
    }

    public function test_6_ledger_posting_on_successful_intake(): void
    {
        $createAction = app(CreateReverseShipmentAction::class);
        $transitionAction = app(TransitionReverseShipmentAction::class);
        $intakeAction = app(ProcessPlantIntakeAction::class);

        $shipment = $createAction->execute([
            'reverse_reason' => 'material_sekunder',
            'source_party_id' => $this->adminUser->id,
            'material_code' => 'PET_FLAKES',
            'estimated_weight_grams' => 40000,
            'origin_location_id' => $this->hubLocation->id,
            'destination_facility_id' => $this->plantLocation->id,
        ], $this->adminUser);

        $shipment->update([
            'plant_id' => $this->plant->code,
            'grade_code' => 'A',
            'reverse_manifest_number' => 'MNF-REV-TEST-006',
            'reverse_chain_hash' => hash('sha256', 'test-chain-6'),
            'carrier_cost_idr' => 150000,
            'source_party_id' => $this->adminUser->id,
        ]);

        $shipment = $transitionAction->execute($shipment, ShipmentStatus::PickedUp, $this->hubOperator);
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::AtHub, $this->hubOperator);
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::DeliveredToPlant, $this->hubOperator);

        $intakeAction->execute(
            shipment: $shipment,
            netWeightGrams: 40000,
            gradeCode: 'A',
            actor: $this->plantReceiver
        );

        // Nilai lot: 40kg * 12.500 = Rp 500.000
        $clearingAccount = LedgerAccount::where('code', 'mfg:recycled_material_clearing:IDR')->first();
        $inventoryAccount = LedgerAccount::where('code', 'inv:materials:IDR')->first();

        $this->assertNotNull($clearingAccount);
        $this->assertNotNull($inventoryAccount);

        // Persediaan bertambah (debit = negatif di single sign convention)
        $this->assertEquals(-500000, $inventoryAccount->cached_balance);
        // Kliring diselesaikan oleh nota supplier
        $this->assertEquals(0, $clearingAccount->cached_balance);
    }

    public function test_7_carrier_cost_accrual_ledger(): void
    {
        $accrueAction = app(AccrueCarrierCostAction::class);

        $shipment = Shipment::create([
            'tracking_number' => 'TRK-COST-001',
            'shipper_id' => $this->adminUser->id,
            'consignee_name' => 'Plant',
            'consignee_phone' => '08123',
            'consignee_address' => ['city' => 'JKT'],
            'origin_location_id' => $this->hubLocation->id,
            'destination_location_id' => $this->plantLocation->id,
            'status' => ShipmentStatus::DeliveredToPlant,
            'is_reverse' => true,
            'carrier_cost_idr' => 250000,
        ]);

        $leg = \Modules\Logistics\Domain\Models\ShipmentLeg::create([
            'shipment_id' => $shipment->id,
            'carrier_id' => $this->carrier->id,
            'leg_sequence' => 1,
            'mode' => \Modules\Logistics\Domain\Enums\TransportMode::ROAD,
            'origin_location_id' => $this->hubLocation->id,
            'destination_location_id' => $this->plantLocation->id,
            'estimated_departure' => now(),
            'estimated_arrival' => now()->addHours(2),
            'status' => 'completed',
        ]);

        $accrueAction->execute($leg, 250000);

        $carrierCostAccount = LedgerAccount::where('code', 'lgx:carrier_cost:IDR')->first();
        $this->assertNotNull($carrierCostAccount);
        $this->assertEquals(-250000, $carrierCostAccount->cached_balance);
    }

    public function test_8_esg_k3_emission_benefit_recorder(): void
    {
        $esgRecorder = app(EmissionBenefitRecorder::class);

        $savedGrams = $esgRecorder->recordAvoidedEmission(
            sourceType: 'reverse_shipment',
            sourceId: 99,
            materialCode: 'PET_FLAKES',
            quantityGrams: 100000, // 100 kg
            at: CarbonImmutable::now(),
            idempotencyKey: 'esg:test:benefit:99'
        );

        // Faktor default 2100 g CO2e / kg -> 100 kg * 2100 = 210.000 g CO2e
        $this->assertEquals(210000, $savedGrams);

        $this->assertDatabaseHas('esg_avoided_emissions', [
            'material_code' => 'PET_FLAKES',
            'quantity_grams' => 100000,
            'avoided_co2e_grams' => 210000,
        ]);
    }

    public function test_9_min_cost_flow_route_optimizer_api(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('logistics.reverse.optimize'), [
                'sources' => [
                    ['id' => 'S1', 'supply' => 30],
                    ['id' => 'S2', 'supply' => 20],
                    ['id' => 'S3', 'supply' => 25],
                ],
                'plants' => [
                    ['id' => 'P1', 'demand' => 35],
                    ['id' => 'P2', 'demand' => 40],
                ],
                'routes' => [
                    ['from' => 'S1', 'to' => 'P1', 'cost' => 3, 'capacity' => 30],
                    ['from' => 'S1', 'to' => 'P2', 'cost' => 5, 'capacity' => 30],
                    ['from' => 'S2', 'to' => 'P1', 'cost' => 4, 'capacity' => 5],
                    ['from' => 'S2', 'to' => 'P2', 'cost' => 2, 'capacity' => 15],
                    ['from' => 'S3', 'to' => 'P1', 'cost' => 6, 'capacity' => 25],
                    ['from' => 'S3', 'to' => 'P2', 'cost' => 4, 'capacity' => 25],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'optimal' => true,
            'total_flow' => 75,
            'total_cost' => 240,
        ]);

        $allocations = $response->json('allocations');
        $this->assertNotEmpty($allocations);
    }

    public function test_10_idor_protection_customer_forbidden_on_mutations(): void
    {
        $shipment = Shipment::create([
            'tracking_number' => 'TRK-IDOR-001',
            'shipper_id' => $this->adminUser->id,
            'consignee_name' => 'Plant',
            'consignee_phone' => '08123',
            'consignee_address' => ['city' => 'JKT'],
            'origin_location_id' => $this->hubLocation->id,
            'destination_location_id' => $this->plantLocation->id,
            'status' => ShipmentStatus::Requested,
            'is_reverse' => true,
        ]);

        // Role customer dilarang membuat reverse shipment
        $this->actingAs($this->customerUser)
            ->post(route('logistics.reverse.shipments.store'), [
                'sender_id' => $this->customerUser->id,
                'recipient_id' => $this->customerUser->id,
                'reverse_type' => 'recycling',
                'material_code' => 'PET_FLAKES',
                'quality_grade' => 'A',
                'weight_grams' => 10000,
                'plant_id' => $this->plant->code,
            ])
            ->assertStatus(403);

        // Role customer dilarang transisi reverse shipment
        $this->actingAs($this->customerUser)
            ->post(route('logistics.reverse.shipments.transition', $shipment->id), [
                'target_status' => 'picked_up',
            ])
            ->assertStatus(403);
    }

    public function test_11_audit_circular_passes_on_clean_data(): void
    {
        $this->seed(\Database\Seeders\ReverseLogisticsT1Seeder::class);

        $exitCode = Artisan::call('logi:circular-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_12_audit_circular_fails_on_corrupt_data(): void
    {
        $fixture = new CircularAuditCorruptionFixture();
        $fixture->corrupt();

        $exitCode = Artisan::call('logi:circular-audit');
        $this->assertEquals(1, $exitCode);
    }

    public function test_13_recycled_materials_intake_controller_endpoint(): void
    {
        $createAction = app(CreateReverseShipmentAction::class);
        $transitionAction = app(TransitionReverseShipmentAction::class);

        $shipment = $createAction->execute([
            'reverse_reason' => 'material_sekunder',
            'source_party_id' => $this->adminUser->id,
            'material_code' => 'PET_FLAKES',
            'estimated_weight_grams' => 30000,
            'origin_location_id' => $this->hubLocation->id,
            'destination_facility_id' => $this->plantLocation->id,
        ], $this->adminUser);

        $shipment->update([
            'plant_id' => $this->plant->code,
            'grade_code' => 'A',
            'reverse_manifest_number' => 'MNF-REV-TEST-013',
            'reverse_chain_hash' => hash('sha256', 'test-chain-13'),
            'carrier_cost_idr' => 150000,
            'source_party_id' => $this->adminUser->id,
        ]);

        $shipment = $transitionAction->execute($shipment, ShipmentStatus::PickedUp, $this->hubOperator);
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::AtHub, $this->hubOperator);
        $shipment = $transitionAction->execute($shipment, ShipmentStatus::DeliveredToPlant, $this->hubOperator);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('manufacturing.recycled-materials.intake'), [
                'shipment_id' => $shipment->id,
                'decision' => 'accept',
                'verified_weight_grams' => 30000,
                'quality_grade' => 'A',
                'notes' => 'Intake lolos QC pabrik',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'decision' => 'accept',
            'total_value_idr' => 375000, // 30kg * 12.500
        ]);

        $this->assertDatabaseHas('mfg_recycled_receipts', [
            'reverse_shipment_id' => $shipment->id,
            'quantity_grams' => 30000,
        ]);
    }
}
