<?php

declare(strict_types=1);

namespace Modules\Proptech\tests\Feature\Bim;

use App\Models\User;
use App\Quality\Audit\Fixtures\ProptechBimCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Proptech\Application\Services\BimTwinService;
use Modules\Proptech\Domain\Models\Bim\TwinComponent;
use Tests\TestCase;

class BimVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected BimTwinService $bimService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->bimService = app(BimTwinService::class);
    }

    public function test_a_authorized_user_can_access_bim_index_and_run_simulation(): void
    {
        $manager = User::factory()->create(['role' => 'facility_manager']);

        $model = $this->bimService->createOrReviseModel(
            projectCode: 'PRJ-HTTP-01',
            name: 'Http Test Model',
            metadata: ['author' => 'Test']
        );

        TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'CHL-HTTP-01',
            'wbs_node_code' => 'WBS-HTTP-01',
            'category' => 'HVAC_CHILLER',
            'name' => 'Chiller Unit A',
            'completion_pct' => 100,
            'status' => 'INSTALLED',
        ]);

        // 1. Access BIM index view
        $response = $this->actingAs($manager)->get(route('proptech.bim.index'));
        $response->assertOk();
        $response->assertSeeText('Digital Twin & BIM Lifecycle Explorer');
        $response->assertSeeText('PRJ-HTTP-01');

        // 2. Run simulation sandbox
        $simResponse = $this->actingAs($manager)->post(route('proptech.bim.simulate', $model), [
            'ambient_temp_c' => 34.0,
        ]);
        $simResponse->assertRedirect(route('proptech.bim.index'));
        $simResponse->assertSessionHas('simulation_result');

        // 3. Post WBS capitalization
        $capResponse = $this->actingAs($manager)->post(route('proptech.bim.capitalize'), [
            'wbs_node_code' => 'WBS-HTTP-01',
            'milestone_value_idr' => 200_000_000,
        ]);
        $capResponse->assertRedirect(route('proptech.bim.index'));
        $capResponse->assertSessionHas('success');
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('proptech.bim.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('proptech.bim.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_bim_audit_passes_cleanly(): void
    {
        $code = Artisan::call('prp:audit-bim');
        $this->assertSame(0, $code);
    }

    public function test_e_prp_audit_bim_fails_when_data_is_corrupted(): void
    {
        $fixture = new ProptechBimCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('prp:audit-bim');
        $this->assertSame(1, $code);
    }
}
