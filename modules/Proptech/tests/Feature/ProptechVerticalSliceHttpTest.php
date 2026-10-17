<?php

declare(strict_types=1);

namespace Modules\Proptech\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\PrpAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Proptech\database\seeders\ProptechDemoSeeder;
use Modules\Proptech\Domain\Models\BuildingZone;
use Tests\TestCase;

class ProptechVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new ProptechDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_proptech_index_and_generate_bill(): void
    {
        $manager = User::factory()->create(['role' => 'facility_manager']);
        $zone = BuildingZone::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($manager)->get(route('proptech.index'));
        $response->assertOk();
        $response->assertSeeText('Proptech & Smart Building Operations');
        $response->assertSee($zone->zone_code);

        // 2. Generate bill
        $billResponse = $this->actingAs($manager)->post(route('proptech.zones.bill', $zone), [
            'tenant_id' => 1,
            'billing_date' => now()->toDateString(),
        ]);

        $billResponse->assertRedirect(route('proptech.index'));
        $billResponse->assertSessionHas('success');

        $this->assertDatabaseHas('prp_zone_utility_billings', [
            'zone_id' => $zone->id,
            'tenant_id' => 1,
            'status' => 'BILLED',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($guest)->get(route('proptech.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('proptech.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_proptech_audit_passes_cleanly(): void
    {
        $code = Artisan::call('prp:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_prp_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new PrpAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('prp:audit');
        $this->assertSame(1, $code);
    }
}
