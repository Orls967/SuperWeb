<?php

namespace Modules\Agri\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agri\Application\Services\AgriService;
use Modules\Agri\Domain\Models\AgriColdChainLog;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Agri\Domain\Models\AgriFarmer;
use Tests\TestCase;

class AgriTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_farmer_and_create_contract(): void
    {
        $service = app(AgriService::class);

        $farmer = $service->registerFarmer([
            'farmer_code' => 'FRM-SUBANG-01',
            'farmer_group_name' => 'Kelompok Tani Makmur Subang',
            'full_name' => 'Pak Asep Sunandar',
            'phone_number' => '081234567890',
            'land_area_hectares' => 2.5,
            'primary_commodity' => 'cabe_merah',
        ]);

        $this->assertInstanceOf(AgriFarmer::class, $farmer);

        $contract = $service->createFarmingContract([
            'contract_number' => 'AGR-CTR-2026-001',
            'farmer_id' => $farmer->id,
            'commodity' => 'cabe_merah',
            'target_yield_kg' => 5000.0,
            'seed_advance_value_idr' => 2000000,
            'fertilizer_advance_value_idr' => 3000000,
            'guaranteed_floor_price_idr_per_kg' => 25000,
        ]);

        $this->assertInstanceOf(AgriContract::class, $contract);
        $this->assertEquals(5000000, $contract->total_advance_deductible_idr);
    }

    public function test_can_receive_harvest_and_deduct_advances(): void
    {
        $service = app(AgriService::class);

        $farmer = $service->registerFarmer([
            'farmer_group_name' => 'KT Berkah',
            'full_name' => 'Pak Joko',
            'phone_number' => '081299998888',
            'land_area_hectares' => 1.0,
            'primary_commodity' => 'cabe_merah',
        ]);

        $contract = $service->createFarmingContract([
            'farmer_id' => $farmer->id,
            'commodity' => 'cabe_merah',
            'target_yield_kg' => 2000.0,
            'seed_advance_value_idr' => 1000000,
            'fertilizer_advance_value_idr' => 1000000,
            'guaranteed_floor_price_idr_per_kg' => 30000,
        ]);

        // Receive 200 kg Grade A: gross 200 * 30.000 = 6.000.000, advance deduction = 2.000.000, net = 4.000.000
        $batch = $service->receiveHarvestAtCollectionCenter([
            'collection_center_id' => 'CC-SUBANG-01',
            'contract_id' => $contract->id,
            'gross_weight_kg' => 200.0,
            'grade' => 'GRADE_A',
            'destination_unit' => 'RESTO_CK01',
        ]);

        $this->assertInstanceOf(AgriCollectionBatch::class, $batch);
        $this->assertEquals(6000000, $batch->gross_payout_idr);
        $this->assertEquals(2000000, $batch->advance_deduction_idr);
        $this->assertEquals(4000000, $batch->net_payout_idr);

        // Advance should now be zero on the contract
        $contract->refresh();
        $this->assertEquals(0, $contract->total_advance_deductible_idr);

        // Receive Grade B (90% of 30.000 = 27.000 / kg)
        $batchB = $service->receiveHarvestAtCollectionCenter([
            'collection_center_id' => 'CC-SUBANG-01',
            'contract_id' => $contract->id,
            'gross_weight_kg' => 100.0,
            'grade' => 'GRADE_B',
        ]);
        $this->assertEquals(27000, $batchB->buying_price_per_kg);
        $this->assertEquals(2700000, $batchB->gross_payout_idr);
        $this->assertEquals(2700000, $batchB->net_payout_idr);
    }

    public function test_can_log_cold_chain_iot_telemetry(): void
    {
        $service = app(AgriService::class);

        $farmer = $service->registerFarmer([
            'farmer_group_name' => 'KT Organik',
            'full_name' => 'Bu Siti',
            'phone_number' => '081377776666',
            'land_area_hectares' => 1.5,
            'primary_commodity' => 'sayur_hidroponik',
        ]);

        $contract = $service->createFarmingContract([
            'farmer_id' => $farmer->id,
            'commodity' => 'sayur_hidroponik',
            'target_yield_kg' => 500.0,
            'guaranteed_floor_price_idr_per_kg' => 15000,
        ]);

        $batch = $service->receiveHarvestAtCollectionCenter([
            'collection_center_id' => 'CC-LEMBANG-01',
            'contract_id' => $contract->id,
            'gross_weight_kg' => 100.0,
        ]);

        $log = $service->recordColdChainReading([
            'batch_id' => $batch->id,
            'reefer_truck_id' => 'TRK-REEFER-09',
            'temperature_celsius' => 4.5,
            'humidity_percentage' => 88.0,
        ]);

        $this->assertInstanceOf(AgriColdChainLog::class, $log);
        $this->assertEquals('optimal', $log->cold_chain_status);
    }

    public function test_agri_audit_passes_cleanly(): void
    {
        $service = app(AgriService::class);

        $audit = $service->auditAgri();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEmpty($audit['discrepancies']);
    }

    public function test_agri_web_index_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('agri.index'));
        $response->assertStatus(200);
        $response->assertSee('Agribusiness, Contract Farming & Cold Chain');
    }
}
