<?php

declare(strict_types=1);

namespace Modules\Wealth\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\WmAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Wealth\database\seeders\WealthDemoSeeder;
use Modules\Wealth\Domain\Models\WmProfile;
use Tests\TestCase;

class WealthVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new WealthDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_wealth_index_and_invest(): void
    {
        $manager = User::factory()->create(['role' => 'wealth_manager']);
        $profile = WmProfile::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($manager)->get(route('wealth.index'));
        $response->assertOk();
        $response->assertSeeText('Robo-Advisor Wealth Management & Treasury Yield');
        $response->assertSee(strtoupper($profile->risk_profile));

        // 2. Execute surplus invest
        $investResponse = $this->actingAs($manager)->post(route('wealth.profiles.invest', $profile), [
            'wallet_balance_idr' => 50000000,
        ]);

        $investResponse->assertRedirect(route('wealth.index'));
        $investResponse->assertSessionHas('success');

        $this->assertDatabaseHas('wm_holdings', [
            'profile_id' => $profile->id,
            'asset_type' => 'mutual_fund',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'supplier']);

        $response = $this->actingAs($guest)->get(route('wealth.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('wealth.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_wealth_audit_passes_cleanly(): void
    {
        $code = Artisan::call('wm:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_wm_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new WmAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('wm:audit');
        $this->assertSame(1, $code);
    }
}
