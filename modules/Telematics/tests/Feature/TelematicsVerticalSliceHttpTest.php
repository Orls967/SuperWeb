<?php

declare(strict_types=1);

namespace Modules\Telematics\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\TlmAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Telematics\database\seeders\TelematicsDemoSeeder;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

class TelematicsVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new TelematicsDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_telematics_index_and_ingest(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $device = TelematicsDevice::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($manager)->get(route('telematics.index'));
        $response->assertOk();
        $response->assertSeeText('Telematics & Fleet IoT Operations');
        $response->assertSee($device->device_id);

        // 2. Ingest telemetry tick
        $ingestResponse = $this->actingAs($manager)->post(route('telematics.devices.ingest', $device), [
            'oil_temp_c' => 92.5,
            'battery_voltage' => 12.6,
            'fuel_level_pct' => 75.0,
            'dtc_code' => null,
        ]);

        $ingestResponse->assertRedirect(route('telematics.index'));
        $ingestResponse->assertSessionHas('success');

        $this->assertDatabaseHas('oto_telematics_ticks', [
            'device_id' => $device->device_id,
            'oil_temp_c' => 92.5,
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($guest)->get(route('telematics.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('telematics.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_telematics_audit_passes_cleanly(): void
    {
        $code = Artisan::call('tlm:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_tlm_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new TlmAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('tlm:audit');
        $this->assertSame(1, $code);
    }
}
