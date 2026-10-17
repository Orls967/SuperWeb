<?php

declare(strict_types=1);

namespace Modules\Rwa\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\RwaAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Rwa\database\seeders\RwaDemoSeeder;
use Modules\Rwa\Domain\Models\RwaAsset;
use Tests\TestCase;

class RwaVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RwaDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_rwa_index_and_distribute(): void
    {
        $manager = User::factory()->create(['role' => 'asset_manager']);
        $asset = RwaAsset::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($manager)->get(route('rwa.index'));
        $response->assertOk();
        $response->assertSeeText('Real-World Asset (RWA) Tokenization');
        $response->assertSee($asset->token_symbol);

        // 2. Distribute dividend
        $distResponse = $this->actingAs($manager)->post(route('rwa.assets.distribute', $asset), [
            'revenue_pool_idr' => 2000000,
        ]);

        $distResponse->assertRedirect(route('rwa.index'));
        $distResponse->assertSessionHas('success');

        $this->assertDatabaseHas('rwa_dividends', [
            'rwa_asset_id' => $asset->id,
            'total_revenue_pool_idr' => 2000000,
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($guest)->get(route('rwa.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('rwa.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_rwa_audit_passes_cleanly(): void
    {
        $code = Artisan::call('rwa:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_rwa_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new RwaAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('rwa:audit');
        $this->assertSame(1, $code);
    }
}
