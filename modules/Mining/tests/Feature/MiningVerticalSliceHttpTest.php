<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\MinAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Mining\database\seeders\MiningDemoSeeder;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Tests\TestCase;

class MiningVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new MiningDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_mining_index_and_dispatch(): void
    {
        $siteManager = User::factory()->create(['role' => 'site_manager']);
        $pit = MiningPit::firstOrFail();
        $equip = MiningEquipment::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($siteManager)->get(route('mining.index'));
        $response->assertOk();
        $response->assertSeeText('Mining Operations & Fleet Dispatch');
        $response->assertSee($equip->equipment_code);

        // 2. Dispatch equipment
        $dispatchResponse = $this->actingAs($siteManager)->post(route('mining.dispatch', $pit), [
            'equipment_id' => (string) $equip->id,
            'shift_code' => 'DS-01',
            'target_payload_ton' => 95.0,
        ]);

        $dispatchResponse->assertRedirect(route('mining.index'));
        $dispatchResponse->assertSessionHas('success');

        $this->assertDatabaseHas('min_dispatch_runs', [
            'equipment_id' => (string) $equip->id,
            'pit_id' => (string) $pit->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($guest)->get(route('mining.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('mining.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_mining_audit_passes_cleanly(): void
    {
        $code = Artisan::call('min:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_min_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new MinAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('min:audit');
        $this->assertSame(1, $code);
    }
}
