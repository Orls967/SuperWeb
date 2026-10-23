<?php

declare(strict_types=1);

namespace Modules\AutoDex\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AutoDex\Application\Actions\PublishCarListingAction;
use Modules\AutoDex\Application\Actions\SyncGarageInventoryAction;
use Modules\AutoDex\Domain\Models\Brand;
use Tests\TestCase;

class AutoDexGarageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleBrandAndCar(): array
    {
        $brand = Brand::create([
            'name' => 'Honda',
            'country' => 'Japan',
            'category' => 'jdm',
            'is_active' => true,
        ]);

        $car = app(PublishCarListingAction::class)->execute([
            'brand_id' => (string) $brand->id,
            'model' => 'Civic Type R FL5',
            'year_start' => 2023,
            'fuel_type' => 'gasoline',
            'price_idr' => 1400000000,
        ]);

        return [$brand, $car];
    }

    public function test_1_sync_vehicle_to_user_garage_action(): void
    {
        [$brand, $car] = $this->createSampleBrandAndCar();
        $user = User::factory()->create();

        $action = app(SyncGarageInventoryAction::class);
        $garage = $action->execute([
            'user_id' => $user->id,
            'car_id' => (string) $car->id,
            'plate_number' => 'B 1234 CTR',
            'color' => 'Championship White',
            'year_bought' => 2024,
            'nickname' => 'Weekend Track Toy',
        ]);

        $this->assertSame('B 1234 CTR', $garage['plate_number']);
        $this->assertSame('Championship White', $garage['color']);
        $this->assertDatabaseHas('core_vehicles', [
            'user_id' => $user->id,
            'plate_number' => 'B 1234 CTR',
        ]);
    }

    public function test_2_update_existing_garage_vehicle_details(): void
    {
        [$brand, $car] = $this->createSampleBrandAndCar();
        $user = User::factory()->create();
        $action = app(SyncGarageInventoryAction::class);

        $action->execute([
            'user_id' => $user->id,
            'car_id' => (string) $car->id,
            'plate_number' => 'B 8888 TYP',
            'color' => 'Black',
        ]);

        // Re-sync with new color and notes
        $updated = $action->execute([
            'user_id' => $user->id,
            'car_id' => (string) $car->id,
            'plate_number' => 'B 8888 TYP',
            'color' => 'Sonic Gray Pearl',
            'notes' => 'Custom forged wheels installed',
        ]);

        $this->assertSame('Sonic Gray Pearl', $updated['color']);
        $this->assertDatabaseCount('core_vehicles', 1);
    }

    public function test_3_garage_controller_index_view(): void
    {
        [$brand, $car] = $this->createSampleBrandAndCar();
        $user = User::factory()->create(['role' => 'customer']);

        app(SyncGarageInventoryAction::class)->execute([
            'user_id' => $user->id,
            'car_id' => (string) $car->id,
            'plate_number' => 'D 9999 EV',
            'color' => 'Rallye Red',
        ]);

        $response = $this->actingAs($user)->get(route('autodex.garage.index'));
        $response->assertOk();
    }

    public function test_4_car_catalog_controller_index_view(): void
    {
        [$brand, $car] = $this->createSampleBrandAndCar();
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get(route('autodex.index'));
        $response->assertOk();
        $response->assertSeeText($car->model);
    }

    public function test_5_car_catalog_controller_show_view(): void
    {
        [$brand, $car] = $this->createSampleBrandAndCar();
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get(route('autodex.show', $car->slug));
        $response->assertOk();
        $response->assertSeeText($car->model);
    }
}
