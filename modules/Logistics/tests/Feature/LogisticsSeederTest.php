<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\database\seeders\LogisticsSeeder;
use Modules\Logistics\Domain\Models\Aircraft;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Trailer;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Models\Vessel;
use Modules\Logistics\Domain\ValueObjects\ImoNumberValidator;
use Modules\Logistics\Domain\ValueObjects\Iso6346Validator;
use Tests\TestCase;

class LogisticsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogisticsSeeder::class);
    }

    public function test_seeder_initializes_complete_logistics_fleet(): void
    {
        // 30 Trucks
        $this->assertCount(30, Truck::all());
        foreach (Truck::all() as $truck) {
            $this->assertTrue(str_starts_with($truck->plate_number, 'DA'));
            $this->assertNotNull($truck->vehicle);
            $this->assertGreaterThanOrEqual(1, $truck->vehicle->events()->count());
        }

        // 8 Trailers
        $this->assertCount(8, Trailer::all());

        // 4 Vessels with valid IMO
        $this->assertCount(4, Vessel::all());
        foreach (Vessel::all() as $vessel) {
            $this->assertTrue(ImoNumberValidator::isValid($vessel->imo_number));
        }

        // 2 Aircraft
        $this->assertCount(2, Aircraft::all());

        // 300 Containers with valid ISO 6346
        $this->assertCount(300, Container::all());
        foreach (Container::take(50)->get() as $container) {
            $this->assertTrue(Iso6346Validator::isValid($container->container_number));
        }

        // 12 Drivers
        $this->assertCount(12, Driver::all());
        foreach (Driver::all() as $driver) {
            $this->assertTrue($driver->isLicenseValid());
            $this->assertNotNull($driver->user);
            $this->assertEquals('driver', $driver->user->role);
        }

        // Users & Roles
        $this->assertEquals(1, User::where('role', 'logistics_admin')->count());
        $this->assertEquals(3, User::where('role', 'dispatcher')->count());
        $this->assertEquals(4, User::where('role', 'hub_operator')->count());
        $this->assertEquals(5, User::where('role', 'shipper')->count());

        // Shippers have wallet and PIN
        foreach (User::where('role', 'shipper')->get() as $shipper) {
            $this->assertTrue($shipper->walletAccount('IDR')->cached_balance > 0);
        }

        // Bank ledger reconciliation is clean
        $this->artisan('bank:reconcile')->assertSuccessful();
    }
}
