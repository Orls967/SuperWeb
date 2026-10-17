<?php

declare(strict_types=1);

namespace Modules\Ev\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\EvAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Ev\database\seeders\EvDemoSeeder;
use Modules\Ev\Domain\Models\EvCharger;
use Tests\TestCase;

class EvVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EvDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_ev_index_and_reserve(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $charger = EvCharger::firstOrFail();
        $vehicle = Vehicle::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($driver)->get(route('ev.index'));
        $response->assertOk();
        $response->assertSeeText('Ekosistem EV & Jaringan Charging SPKLU');
        $response->assertSee($charger->charger_code);

        // 2. Reserve slot
        $reserveResponse = $this->actingAs($driver)->post(route('ev.chargers.reserve', $charger), [
            'vehicle_id' => $vehicle->id,
            'duration_minutes' => 45,
        ]);

        $reserveResponse->assertRedirect(route('ev.index'));
        $reserveResponse->assertSessionHas('success');

        $this->assertDatabaseHas('oto_ev_sessions', [
            'charger_id' => $charger->id,
            'user_id' => $driver->id,
            'status' => 'reserved',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($guest)->get(route('ev.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('ev.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_ev_audit_passes_cleanly(): void
    {
        $code = Artisan::call('ev:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_ev_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new EvAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('ev:audit');
        $this->assertSame(1, $code);
    }
}
