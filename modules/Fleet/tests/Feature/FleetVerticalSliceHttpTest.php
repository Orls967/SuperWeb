<?php

declare(strict_types=1);

namespace Modules\Fleet\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\FltAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Fleet\database\seeders\FleetDemoSeeder;
use Modules\Fleet\Domain\Models\FleetContract;
use Tests\TestCase;

class FleetVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new FleetDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_fleet_index_and_amortize(): void
    {
        $manager = User::factory()->create(['role' => 'lease_manager']);
        $contract = FleetContract::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($manager)->get(route('fleet.index'));
        $response->assertOk();
        $response->assertSeeText('B2B Fleet Leasing & SLA Management');
        $response->assertSee($contract->contract_number);

        // 2. Amortize month 1
        $amortizeResponse = $this->actingAs($manager)->post(route('fleet.contracts.amortize', $contract), [
            'month_index' => 1,
        ]);

        $amortizeResponse->assertRedirect(route('fleet.index'));
        $amortizeResponse->assertSessionHas('success');

        $this->assertEquals(
            $contract->monthly_rental_idr,
            $contract->fresh()->accumulated_amortized_idr
        );
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($guest)->get(route('fleet.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('fleet.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_fleet_audit_passes_cleanly(): void
    {
        $code = Artisan::call('flt:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_flt_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new FltAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('flt:audit');
        $this->assertSame(1, $code);
    }
}
