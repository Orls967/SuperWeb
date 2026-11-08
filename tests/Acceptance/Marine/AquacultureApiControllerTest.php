<?php

declare(strict_types=1);

namespace Tests\Acceptance\Marine;

use App\Models\User;
use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\ExportReeferShipment;
use Tests\TestCase;

class AquacultureApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_batch_sampling_and_fcr_evaluation_api(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $batch = AquacultureBatch::first();

        // 1. Record Sampling
        $res = $this->postJson(route('marine.aquaculture.batches.sampling', ['id' => $batch->id]), [
            'mean_body_weight_grams' => 26.5,
            'survival_rate_percentage' => 89.0,
        ]);
        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2. Record Water Telemetry
        $res2 = $this->postJson(route('marine.aquaculture.batches.telemetry', ['id' => $batch->id]), [
            'dissolved_oxygen_mg_l' => 6.1,
            'water_temperature_celsius' => 29.0,
            'ph_level' => 7.8,
            'salinity_ppt' => 23.0,
        ]);
        $res2->assertStatus(201)
            ->assertJson(['success' => true]);

        // 3. Evaluate FCR
        $res3 = $this->getJson(route('marine.aquaculture.batches.fcr', ['id' => $batch->id]));
        $res3->assertStatus(200)
            ->assertJsonStructure(['success', 'evaluation' => ['total_feed_used_kg', 'calculated_fcr']]);
    }

    public function test_shipment_health_certificate_and_dispatch_api(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $lot = AquacultureExportLot::first();
        $shipment = ExportReeferShipment::first();

        // 1. Issue Health Certificate
        $res = $this->postJson(route('marine.shipments.health_cert', ['id' => $lot->id]), [
            'inspector_name' => 'Inspector BKIPM Valid',
        ]);
        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2. Issue COO
        $res2 = $this->postJson(route('marine.shipments.coo', ['id' => $lot->id]), [
            'form_type' => 'FORM_D',
            'consignee_country' => 'US',
        ]);
        $res2->assertStatus(200)
            ->assertJson(['success' => true]);

        // 3. Record Cold Chain Telemetry
        $res3 = $this->postJson(route('marine.shipments.telemetry', ['id' => $shipment->id]), [
            'temperature_celsius' => -21.5,
            'humidity_percentage' => 86.0,
            'gps_latitude' => '-7.2001',
            'gps_longitude' => '112.7302',
        ]);
        $res3->assertStatus(201)
            ->assertJson(['success' => true]);
    }
}

