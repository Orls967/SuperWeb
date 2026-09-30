<?php

declare(strict_types=1);

namespace Modules\Logistics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\database\seeders\LogisticsNetworkSeeder;
use Modules\Logistics\Domain\Exceptions\DriverLicenseExpiredException;
use Modules\Logistics\Domain\Exceptions\DrivingHoursLimitExceededException;
use Modules\Logistics\Domain\Exceptions\IncompatibleLicenseException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Tests\TestCase;

class LogisticsDriverTest extends TestCase
{
    use RefreshDatabase;

    protected Location $hubBdj;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogisticsNetworkSeeder::class);
        $this->hubBdj = Location::where('code', 'HUB-BDJ')->firstOrFail();
    }

    public function test_expired_driver_license_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $user->id,
            'driver_number' => 'DRV-BDJ-EXP',
            'license_class' => 'SIM B1 Umum',
            'license_expiry' => now()->subDay(), // Kemarin (kedaluwarsa)
            'home_hub_id' => $this->hubBdj->id,
            'status' => 'available',
        ]);

        $this->assertFalse($driver->isLicenseValid());

        $this->expectException(DriverLicenseExpiredException::class);
        $driver->validateAssignment(requiredLicense: 'SIM B1 Umum', tripMinutes: 60);
    }

    public function test_incompatible_driver_license_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $user->id,
            'driver_number' => 'DRV-BDJ-B1',
            'license_class' => 'SIM B1 Umum',
            'license_expiry' => now()->addYear(),
            'home_hub_id' => $this->hubBdj->id,
            'status' => 'available',
        ]);

        $this->assertTrue($driver->isLicenseValid());

        // Mencoba mengemudikan Tronton / Tractor Head yang membutuhkan SIM B2 Umum
        $this->expectException(IncompatibleLicenseException::class);
        $driver->validateAssignment(requiredLicense: 'SIM B2 Umum', tripMinutes: 60);
    }

    public function test_compatible_driver_license_succeeds(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $user->id,
            'driver_number' => 'DRV-BDJ-B2',
            'license_class' => 'SIM B2 Umum',
            'license_expiry' => now()->addYears(2),
            'home_hub_id' => $this->hubBdj->id,
            'status' => 'available',
        ]);

        // SIM B2 Umum valid untuk armada B2 Umum maupun B1 Umum
        $driver->validateAssignment(requiredLicense: 'SIM B2 Umum', tripMinutes: 120);
        $driver->validateAssignment(requiredLicense: 'SIM B1 Umum', tripMinutes: 120);
        $this->assertTrue(true);
    }

    public function test_uu_22_2009_daily_driving_limit_enforced(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $user->id,
            'driver_number' => 'DRV-BDJ-LIMIT',
            'license_class' => 'SIM B1 Umum',
            'license_expiry' => now()->addYear(),
            'home_hub_id' => $this->hubBdj->id,
            'daily_driving_minutes' => 420, // 7 jam sudah terpakai
            'continuous_driving_minutes' => 60,
            'status' => 'available',
        ]);

        // Trip 60 menit -> total 480 menit (tepat 8 jam): lolos
        $driver->validateAssignment(requiredLicense: 'SIM B1 Umum', tripMinutes: 60);

        // Trip 61 menit -> total 481 menit (> 8 jam / 480 menit): ditolak UU 22/2009 Pasal 90
        $this->expectException(DrivingHoursLimitExceededException::class);
        $driver->validateAssignment(requiredLicense: 'SIM B1 Umum', tripMinutes: 61);
    }

    public function test_uu_22_2009_continuous_driving_limit_and_rest(): void
    {
        $user = User::factory()->create(['role' => 'driver']);
        $driver = Driver::create([
            'user_id' => $user->id,
            'driver_number' => 'DRV-BDJ-REST',
            'license_class' => 'SIM B1 Umum',
            'license_expiry' => now()->addYear(),
            'home_hub_id' => $this->hubBdj->id,
            'daily_driving_minutes' => 200,
            'continuous_driving_minutes' => 210, // 3.5 jam berturut-turut
            'status' => 'available',
        ]);

        // Menambah 35 menit -> 245 menit (> 4 jam / 240 menit berturut-turut): ditolak wajib istirahat
        try {
            $driver->validateAssignment(requiredLicense: 'SIM B1 Umum', tripMinutes: 35);
            $this->fail('Seharusnya melempar DrivingHoursLimitExceededException karena melampaui 4 jam berkendara terus-menerus.');
        } catch (DrivingHoursLimitExceededException $e) {
            $this->assertStringContainsString('melebihi batas 4 jam berturut-turut', $e->getMessage());
        }

        // Driver beristirahat 30 menit sesuai UU 22/2009 Pasal 90 ayat (3)
        $driver->recordRest(30);
        $this->assertEquals(0, $driver->fresh()->continuous_driving_minutes);

        // Setelah istirahat, penugasan trip 120 menit sukses!
        $driver->validateAssignment(requiredLicense: 'SIM B1 Umum', tripMinutes: 120);
        $this->assertTrue(true);
    }

    public function test_drivers_index_page_is_accessible(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get(route('logistics.drivers.index'));
        $response->assertOk();
        $response->assertViewIs('logistics::drivers.index');
        $response->assertSee('Pengemudi & Kru Armada (Drivers)');
        $response->assertSee('UU 22/2009 Pasal 90');
    }
}
