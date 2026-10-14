<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\HubOperator;
use Modules\Logistics\Domain\Models\Location;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LogisticsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $hub = Location::firstOrCreate(
            ['code' => 'HUB-TEST'],
            [
                'name' => 'Hub Test Banjarmasin',
                'type' => LocationType::HUB,
                'city' => 'Banjarmasin',
                'province' => 'Kalimantan Selatan',
                'country' => 'ID',
                'lat_e6' => -3300000,
                'lng_e6' => 114500000,
                'timezone' => 'Asia/Makassar',
                'min_connection_minutes' => 60,
                'is_active' => true,
            ]
        );

        $driverUser = User::factory()->create(['role' => 'driver']);
        Driver::firstOrCreate(
            ['user_id' => $driverUser->id],
            [
                'driver_number' => 'DRV-DEFAULT',
                'home_hub_id' => $hub->id,
                'license_class' => 'SIM B1 Umum',
                'license_expiry' => now()->addYear()->toDateString(),
                'status' => 'available',
            ]
        );
    }

    #[DataProvider('representativeGetRoutesProvider')]
    public function test_logistics_get_routes_allow_authorized_roles(string $route, array $allowedRoles): void
    {
        $hub = Location::first();

        foreach ($allowedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            if ($role === 'driver') {
                Driver::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'driver_number' => 'DRV-'.strtoupper(bin2hex(random_bytes(3))),
                        'home_hub_id' => $hub->id,
                        'license_class' => 'SIM B1 Umum',
                        'license_expiry' => now()->addYear()->toDateString(),
                        'status' => 'available',
                    ]
                );
            }

            if ($role === 'hub_operator') {
                HubOperator::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'hub_id' => $hub->id,
                        'role' => 'operator',
                        'is_active' => true,
                    ]
                );
            }

            $response = $this->actingAs($user)->get(route($route));
            $response->assertOk();
        }
    }

    #[DataProvider('representativeGetRoutesProvider')]
    public function test_logistics_get_routes_deny_unauthorized_roles(string $route, array $allowedRoles): void
    {
        $allPotentialUnauthorized = ['customer', 'mekanik', 'farmer', 'waiter', 'cashier'];
        $unauthorizedRoles = array_diff($allPotentialUnauthorized, $allowedRoles);

        foreach ($unauthorizedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($route));
            $response->assertStatus(403);
        }
    }

    #[DataProvider('representativeGetRoutesProvider')]
    public function test_logistics_get_routes_redirect_guest(string $route, array $allowedRoles): void
    {
        $response = $this->get(route($route));
        $response->assertRedirect('/login');
    }

    public function test_financial_mutation_routes_deny_auditor(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        $response1 = $this->actingAs($auditor)->post(route('logistics.carriers.pay', ['carrier' => 99999]));
        $this->assertSame(403, $response1->status());

        $response2 = $this->actingAs($auditor)->post(route('logistics.claims.pay', ['claim' => 99999]));
        $this->assertSame(403, $response2->status());

        $response3 = $this->actingAs($auditor)->post(route('logistics.customs.pay', ['declaration' => 99999]));
        $this->assertSame(403, $response3->status());
    }

    public static function representativeGetRoutesProvider(): array
    {
        return [
            ['logistics.dashboard', ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
            ['logistics.fleet.index', ['admin', 'auditor', 'dispatcher', 'hub_operator', 'logistics_admin']],
            ['logistics.drivers.index', ['admin', 'auditor', 'dispatcher', 'logistics_admin']],
            ['logistics.dispatch.index', ['admin', 'auditor', 'dispatcher', 'logistics_admin']],
            ['logistics.hub.index', ['admin', 'auditor', 'hub_operator', 'logistics_admin']],
            ['logistics.claims.index', ['admin', 'auditor', 'logistics_admin', 'treasury']],
            ['logistics.carriers.index', ['admin', 'auditor', 'logistics_admin', 'procurement', 'treasury']],
            ['logistics.customs.index', ['admin', 'auditor', 'logistics_admin', 'treasury']],
            ['logistics.fuel.index', ['admin', 'auditor', 'dispatcher', 'logistics_admin']],
            ['logistics.cod.index', ['admin', 'auditor', 'logistics_admin', 'treasury']],
            ['logistics.exceptions.index', ['admin', 'auditor', 'dispatcher', 'logistics_admin']],
            ['logistics.driver.tasks', ['admin', 'driver', 'logistics_admin']],
        ];
    }
}
