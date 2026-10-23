<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature\Autonomous;

use App\Models\User;
use App\Quality\Audit\Fixtures\LogisticsColdChainCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Application\Services\Autonomous\AutonomousLogisticsService;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Tests\TestCase;

class AutonomousLogisticsVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected AutonomousLogisticsService $service;

    protected DroneUnit $drone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(AutonomousLogisticsService::class);

        $this->drone = DroneUnit::create([
            'drone_code' => 'DRN-HTTP-01',
            'model_name' => 'OctoCopter Express',
            'battery_percent' => 100,
            'max_payload_kg' => 5.0,
            'max_range_km' => 15.0,
            'status' => 'IDLE',
        ]);
    }

    public function test_a_authorized_user_can_access_autonomous_index_dispatch_and_release(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        // 1. Access dashboard index
        $response = $this->actingAs($dispatcher)->get(route('logistics.autonomous.index'));
        $response->assertOk();
        $response->assertSeeText('Autonomous Cold-Chain, Blockchain & Drone Robotics');
        $response->assertSeeText('OctoCopter Express');

        // 2. Dispatch a drone mission
        $dispatchResponse = $this->actingAs($dispatcher)->post(route('logistics.autonomous.dispatch'), [
            'drone_unit_id' => $this->drone->id,
            'shipment_code' => 'SHP-COLD-DRONE-01',
            'package_weight_kg' => 2.5,
            'distance_km' => 5.0,
            'destination_geo_hash' => 'qqs387v-banjarmasin-dropzone-01',
        ]);

        $dispatchResponse->assertRedirect(route('logistics.autonomous.index'));
        $dispatchResponse->assertSessionHas('success');
        $this->assertEquals('IN_MISSION', $this->drone->refresh()->status);

        // 3. Create a breach hold and release it
        $breach = $this->service->recordTemperatureCheck(
            shipmentCode: 'SHP-COLD-HTTP-02',
            carrierId: 101,
            recordedTempC: 12.0,
            maxAllowedTempC: 4.0,
            durationMinutes: 20,
            carrierPayableIdr: 3_000_000
        );
        $this->assertNotNull($breach);

        $releaseResponse = $this->actingAs($dispatcher)->post(route('logistics.autonomous.breaches.release', $breach));
        $releaseResponse->assertRedirect(route('logistics.autonomous.index'));
        $releaseResponse->assertSessionHas('success');
        $this->assertEquals('RELEASED', $breach->refresh()->status);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('logistics.autonomous.index'));
        $response->assertForbidden();

        $postResponse = $this->actingAs($customer)->post(route('logistics.autonomous.dispatch'), [
            'drone_unit_id' => $this->drone->id,
            'shipment_code' => 'SHP-FORBIDDEN',
            'package_weight_kg' => 1.0,
            'distance_km' => 2.0,
            'destination_geo_hash' => 'qqs-forbidden',
        ]);
        $postResponse->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('logistics.autonomous.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_cold_chain_audit_passes_cleanly(): void
    {
        $this->service->recordTemperatureCheck(
            shipmentCode: 'SHP-CLEAN-AUDIT-01',
            carrierId: 102,
            recordedTempC: 14.0,
            maxAllowedTempC: 4.0,
            durationMinutes: 15,
            carrierPayableIdr: 4_500_000
        );

        $this->artisan('lgx:audit-coldchain')
            ->assertSuccessful()
            ->expectsOutputToContain('0 selisih');
    }

    public function test_e_lgx_audit_coldchain_fails_when_data_is_corrupted(): void
    {
        $fixture = new LogisticsColdChainCorruptionFixture;
        $corruptKeyword = $fixture->corrupt();

        $this->artisan('lgx:audit-coldchain')
            ->assertFailed()
            ->expectsOutputToContain($corruptKeyword);
    }
}
