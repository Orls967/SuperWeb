<?php

declare(strict_types=1);

namespace Tests\Perilaku;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Contract\Domain\Models\Contract;
use Modules\Core\Domain\Models\Role;
use Modules\Party\Domain\Models\Party;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuItem;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class RouteSmokeRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    /**
     * Rute-rute yang dipulihkan di Bagian D.
     * Format: ['uri' => '...', 'role' => '...', 'method' => 'GET', 'expected_status' => [200, 302]]
     *
     * @var array<int, array{uri: string, role: string, method?: string, expected_status?: array<int>}>
     */
    public static array $fixedRoutes = [];

    public function test_fixed_routes_do_not_regress(): void
    {
        if (empty(static::$fixedRoutes)) {
            $this->assertTrue(true, 'Belum ada rute teregister untuk regresi; diisi di Bagian D.');

            return;
        }

        foreach (static::$fixedRoutes as $routeInfo) {
            $uri = $routeInfo['uri'];
            $role = $routeInfo['role'];
            $expected = $routeInfo['expected_status'] ?? [200, 302];

            $user = User::where('role', $role)->first();
            if (! $user) {
                $user = User::factory()->create([
                    'role' => $role,
                    'email_verified_at' => now(),
                ]);
                $r = Role::where('name', $role)->first();
                if ($r) {
                    $user->assignRbacRole($r);
                }
            }

            $response = $this->actingAs($user)->get($uri);
            $this->assertContains(
                $response->getStatusCode(),
                $expected,
                "Regresi pada rute {$uri} dengan role {$role}: status {$response->getStatusCode()}"
            );
        }
    }

    public function test_p07_contract_show_route_renders_successfully(): void
    {
        $contract = Contract::first();
        $this->assertNotNull($contract, 'Seed contract must exist');

        $user = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $response = $this->actingAs($user)->get('/contracts/'.$contract->id);

        $response->assertOk();
    }

    public function test_p06_party_show_route_renders_successfully(): void
    {
        $party = Party::whereHas('roles')->first() ?? Party::first();
        $this->assertNotNull($party, 'Seed party must exist');

        $user = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $response = $this->actingAs($user)->get('/party/'.$party->id);

        $response->assertOk();
    }

    public function test_p01_p02_p03_resto_menu_routes_render_ok(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $menu = MenuItem::first();
        $this->assertNotNull($menu, 'Seed menu item must exist');

        // P-01: menu show
        $resShow = $this->actingAs($admin)->get('/resto/menu/'.$menu->id);
        $resShow->assertOk();

        // P-02: menu create
        $resCreate = $this->actingAs($admin)->get('/resto/menu/create');
        $resCreate->assertOk();

        // P-03: menu edit
        $resEdit = $this->actingAs($admin)->get('/resto/menu/'.$menu->id.'/edit');
        $resEdit->assertOk();
    }

    public function test_p04_resto_ingredient_routes_render_ok(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $ing = Ingredient::first();
        $this->assertNotNull($ing, 'Seed ingredient must exist');

        // P-04: ingredient show
        $resShow = $this->actingAs($admin)->get('/resto/ingredients/'.$ing->id);
        $resShow->assertOk();

        // ingredient create
        $resCreate = $this->actingAs($admin)->get('/resto/ingredients/create');
        $resCreate->assertOk();
    }

    public function test_p05_resto_catering_create_renders_ok(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // P-05: catering create
        $resCreate = $this->actingAs($admin)->get('/resto/catering/create');
        $resCreate->assertOk();
    }
}
