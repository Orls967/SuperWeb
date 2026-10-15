<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Distribution\Domain\Models\Distributor;
use Tests\TestCase;

class DistributorPortalSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_without_query_receives_200_with_distributor_list_selector(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        $d1 = Distributor::create([
            'code' => 'DST-ALPHA',
            'name' => 'Distributor Alpha',
            'kind' => 'distributor',
            'tier' => 'gold',
            'status' => 'approved',
            'credit_limit_idr' => 100_000_000,
            'credit_exposure_idr' => 10_000_000,
            'payment_terms_days' => 30,
        ]);

        $d2 = Distributor::create([
            'code' => 'DST-BETA',
            'name' => 'Distributor Beta',
            'kind' => 'distributor',
            'tier' => 'silver',
            'status' => 'approved',
            'credit_limit_idr' => 50_000_000,
            'credit_exposure_idr' => 5_000_000,
            'payment_terms_days' => 14,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('distribution.portal.home'));

        $response->assertOk();
        $response->assertSee('Supervisi Portal Distributor — Pilih Distributor');
        $response->assertSee('DST-ALPHA');
        $response->assertSee('Distributor Alpha');
        $response->assertSee('DST-BETA');
        $response->assertSee('Distributor Beta');
    }

    public function test_admin_with_distributor_query_receives_200_with_data_and_audit_log(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        $distributor = Distributor::create([
            'code' => 'DST-TARGET',
            'name' => 'Distributor Khusus Target',
            'kind' => 'distributor',
            'tier' => 'platinum',
            'status' => 'approved',
            'credit_limit_idr' => 250_000_000,
            'credit_exposure_idr' => 45_000_000,
            'payment_terms_days' => 60,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('distribution.portal.home', ['distributor' => $distributor->id]));

        $response->assertOk();
        $response->assertSee('Portal Distributor — DST-TARGET');
        $response->assertSee('Tier platinum');

        $this->assertDatabaseHas('core_audit_logs', [
            'action' => 'distribution.portal.admin_view',
            'user_id' => $admin->id,
            'auditable_type' => Distributor::class,
            'auditable_id' => $distributor->id,
        ]);
    }

    public function test_non_admin_distributor_attempting_to_select_distributor_receives_403(): void
    {
        $user1 = User::factory()->create(['role' => 'distributor']);
        $user2 = User::factory()->create(['role' => 'distributor']);

        $d1 = Distributor::create([
            'code' => 'DST-USER1',
            'name' => 'Distributor Milik User 1',
            'owner_user_id' => $user1->id,
            'kind' => 'distributor',
            'tier' => 'bronze',
            'status' => 'approved',
            'credit_limit_idr' => 10_000_000,
            'credit_exposure_idr' => 0,
            'payment_terms_days' => 7,
        ]);

        $d2 = Distributor::create([
            'code' => 'DST-USER2',
            'name' => 'Distributor Milik User 2',
            'owner_user_id' => $user2->id,
            'kind' => 'distributor',
            'tier' => 'gold',
            'status' => 'approved',
            'credit_limit_idr' => 100_000_000,
            'credit_exposure_idr' => 0,
            'payment_terms_days' => 30,
        ]);

        // User 1 mencoba memilih distributor 2 via ?distributor=X
        $response = $this->actingAs($user1)
            ->get(route('distribution.portal.home', ['distributor' => $d2->id]));

        $response->assertForbidden();
    }

    public function test_distributor_user_without_query_views_own_distributor(): void
    {
        $user = User::factory()->create(['role' => 'distributor']);

        $distributor = Distributor::create([
            'code' => 'DST-MYSELF',
            'name' => 'Distributor Sendiri',
            'owner_user_id' => $user->id,
            'kind' => 'distributor',
            'tier' => 'silver',
            'status' => 'approved',
            'credit_limit_idr' => 50_000_000,
            'credit_exposure_idr' => 0,
            'payment_terms_days' => 14,
        ]);

        $response = $this->actingAs($user)
            ->get(route('distribution.portal.home'));

        $response->assertOk();
        $response->assertSee('Portal Distributor — DST-MYSELF');
    }

    public function test_distributor_user_without_linked_distributor_receives_403(): void
    {
        $user = User::factory()->create(['role' => 'distributor']);

        $response = $this->actingAs($user)
            ->get(route('distribution.portal.home'));

        $response->assertForbidden();
    }
}
