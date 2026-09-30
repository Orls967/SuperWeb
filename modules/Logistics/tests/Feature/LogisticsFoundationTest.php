<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Policies\DriverPolicy;
use Modules\Logistics\Policies\FleetPolicy;
use Modules\Logistics\Policies\LanePolicy;
use Modules\Logistics\Policies\LocationPolicy;
use Modules\Logistics\Policies\ShipmentPolicy;
use Modules\Shared\Application\MenuRegistry;
use Tests\TestCase;

class LogisticsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_provider_registers_menu_items_under_logistik_group(): void
    {
        $registry = app(MenuRegistry::class);

        $admin = User::factory()->create(['role' => 'admin']);
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $driver = User::factory()->create(['role' => 'driver']);
        $shipper = User::factory()->create(['role' => 'shipper']);

        $adminGroups = $registry->getGroupedItemsForUser($admin);
        $this->assertArrayHasKey('Logistik', $adminGroups);
        $labels = array_map(fn ($item) => $item->label, $adminGroups['Logistik']);
        $this->assertContains('Jaringan & Hub', $labels);
        $this->assertContains('Armada & Kontainer', $labels);
        $this->assertContains('Pengemudi & Kru', $labels);
        $this->assertContains('Pengiriman & Resi', $labels);

        $dispatcherGroups = $registry->getGroupedItemsForUser($dispatcher);
        $this->assertArrayHasKey('Logistik', $dispatcherGroups);

        $driverGroups = $registry->getGroupedItemsForUser($driver);
        $this->assertArrayHasKey('Logistik', $driverGroups);
        $driverLabels = array_map(fn ($item) => $item->label, $driverGroups['Logistik']);
        $this->assertContains('Tugas Driver', $driverLabels);
        $this->assertNotContains('Jaringan & Hub', $driverLabels);

        $shipperGroups = $registry->getGroupedItemsForUser($shipper);
        $this->assertArrayHasKey('Logistik', $shipperGroups);
        $shipperLabels = array_map(fn ($item) => $item->label, $shipperGroups['Logistik']);
        $this->assertContains('Pengiriman & Resi', $shipperLabels);
    }

    public function test_user_model_role_helpers_for_logistics(): void
    {
        $admin = new User(['role' => 'logistics_admin']);
        $dispatcher = new User(['role' => 'dispatcher']);
        $hubOp = new User(['role' => 'hub_operator']);
        $driver = new User(['role' => 'driver']);
        $shipper = new User(['role' => 'shipper']);

        $this->assertTrue($admin->isLogisticsAdmin());
        $this->assertTrue($admin->isStaff());

        $this->assertTrue($dispatcher->isDispatcher());
        $this->assertTrue($dispatcher->isStaff());

        $this->assertTrue($hubOp->isHubOperator());
        $this->assertTrue($hubOp->isStaff());

        $this->assertTrue($driver->isDriver());
        $this->assertTrue($driver->isStaff());

        $this->assertTrue($shipper->isShipper());
        $this->assertFalse($shipper->isStaff());
    }

    public function test_policies_per_resource(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $logAdmin = User::factory()->create(['role' => 'logistics_admin']);
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $hubOp = User::factory()->create(['role' => 'hub_operator']);
        $driver = User::factory()->create(['role' => 'driver']);
        $shipper = User::factory()->create(['role' => 'shipper']);
        $regular = User::factory()->create(['role' => 'customer']);

        $locPolicy = new LocationPolicy;
        $this->assertTrue($locPolicy->create($admin));
        $this->assertTrue($locPolicy->create($logAdmin));
        $this->assertFalse($locPolicy->create($dispatcher));
        $this->assertFalse($locPolicy->create($shipper));

        $lanePolicy = new LanePolicy;
        $this->assertTrue($lanePolicy->create($dispatcher));
        $this->assertFalse($lanePolicy->create($hubOp));

        $fleetPolicy = new FleetPolicy;
        $this->assertTrue($fleetPolicy->viewAny($hubOp));
        $this->assertFalse($fleetPolicy->create($dispatcher));
        $this->assertTrue($fleetPolicy->create($logAdmin));

        $driverPolicy = new DriverPolicy;
        $this->assertTrue($driverPolicy->viewAny($dispatcher));
        $this->assertFalse($driverPolicy->viewAny($shipper));
        $this->assertTrue($driverPolicy->view($driver, (object) ['user_id' => $driver->id]));
        $this->assertFalse($driverPolicy->view($regular, (object) ['user_id' => $driver->id]));

        $shipmentPolicy = new ShipmentPolicy;
        $this->assertTrue($shipmentPolicy->create($shipper));
        $this->assertFalse($shipmentPolicy->create($regular));
        $this->assertTrue($shipmentPolicy->view($shipper, (object) ['shipper_id' => $shipper->id, 'driver_id' => null]));
        $this->assertFalse($shipmentPolicy->view($regular, (object) ['shipper_id' => $shipper->id, 'driver_id' => null]));
    }

    public function test_logistics_dashboard_route_is_accessible(): void
    {
        $user = User::factory()->create(['role' => 'logistics_admin']);

        $response = $this->actingAs($user)->get(route('logistics.dashboard'));
        $response->assertOk();
        $response->assertViewIs('logistics::dashboard');
    }
}
