<?php

declare(strict_types=1);

namespace Modules\AutoDex\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AutoDex\Application\Actions\AddCarToWishlistAction;
use Modules\AutoDex\Application\Actions\PublishCarListingAction;
use Modules\AutoDex\Application\Actions\RemoveCarFromWishlistAction;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoDex\Domain\Models\Wishlist;
use Tests\TestCase;

class AutoDexCatalogAndWishlistTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleBrand(string $name = 'Porsche'): Brand
    {
        return Brand::create([
            'name' => $name,
            'country' => 'Germany',
            'category' => 'euro',
            'is_active' => true,
        ]);
    }

    public function test_1_publish_car_listing_action(): void
    {
        $brand = $this->createSampleBrand('Porsche');
        $action = app(PublishCarListingAction::class);

        $car = $action->execute([
            'brand_id' => (string) $brand->id,
            'model' => '911 GT3 RS',
            'year_start' => 2024,
            'body_type' => 'coupe',
            'fuel_type' => 'gasoline',
            'horsepower' => 525,
            'torque_nm' => 465,

            'zero_to_100' => '3.2',
            'price_idr' => 9500000000,
        ]);

        $this->assertInstanceOf(Car::class, $car);
        $this->assertTrue($car->is_active);
        $this->assertSame('911 GT3 RS', $car->model);
        $this->assertNotEmpty($car->slug);
    }

    public function test_2_add_car_to_wishlist_action(): void
    {
        $brand = $this->createSampleBrand('Tesla');
        $car = app(PublishCarListingAction::class)->execute([
            'brand_id' => $brand->id,
            'model' => 'Model S Plaid',
            'year_start' => 2023,
            'fuel_type' => 'electric',
            'price_idr' => 2800000000,
        ]);

        $user = User::factory()->create();
        $action = app(AddCarToWishlistAction::class);

        $wishlist = $action->execute([
            'user_id' => $user->id,
            'car_id' => $car->id,
            'priority' => 3,
            'notes' => 'Dream track car',
        ]);

        $this->assertInstanceOf(Wishlist::class, $wishlist);
        $this->assertSame(3, $wishlist->priority);
        $this->assertSame('Dream track car', $wishlist->notes);
        $this->assertDatabaseHas('dex_wishlists', [
            'user_id' => $user->id,
            'car_id' => $car->id,
        ]);
    }

    public function test_3_update_existing_car_wishlist_priority(): void
    {
        $brand = $this->createSampleBrand('BMW');
        $car = app(PublishCarListingAction::class)->execute([
            'brand_id' => $brand->id,
            'model' => 'M3 Competition',
            'year_start' => 2023,
        ]);

        $user = User::factory()->create();
        $action = app(AddCarToWishlistAction::class);

        $action->execute([
            'user_id' => $user->id,
            'car_id' => $car->id,
            'priority' => 1,
        ]);

        // Re-execute to update priority to 5
        $updated = $action->execute([
            'user_id' => $user->id,
            'car_id' => $car->id,
            'priority' => 5,
            'notes' => 'Priority escalated to immediate purchase',
        ]);

        $this->assertSame(5, $updated->priority);
        $this->assertDatabaseCount('dex_wishlists', 1);
    }

    public function test_4_remove_car_from_wishlist_action(): void
    {
        $brand = $this->createSampleBrand('Audi');
        $car = app(PublishCarListingAction::class)->execute([
            'brand_id' => $brand->id,
            'model' => 'RS6 Avant',
            'year_start' => 2022,
        ]);

        $user = User::factory()->create();
        app(AddCarToWishlistAction::class)->execute([
            'user_id' => $user->id,
            'car_id' => $car->id,
        ]);

        $removeAction = app(RemoveCarFromWishlistAction::class);
        $removed = $removeAction->execute([
            'user_id' => $user->id,
            'car_id' => $car->id,
        ]);

        $this->assertTrue($removed);
        $this->assertDatabaseMissing('dex_wishlists', [
            'user_id' => $user->id,
            'car_id' => $car->id,
        ]);
    }

    public function test_5_car_catalog_scopes_and_attributes(): void
    {
        $brand = $this->createSampleBrand('Hyundai');
        $car = app(PublishCarListingAction::class)->execute([
            'brand_id' => $brand->id,
            'model' => 'Ioniq 5 N',
            'year_start' => 2024,
            'body_type' => 'suv',
            'fuel_type' => 'electric',
            'price_idr' => 1350000000,
        ]);

        $this->assertTrue($car->isElectric());
        $this->assertStringContainsString('Ioniq 5 N', $car->full_name);
        $this->assertStringContainsString('M', $car->formatted_price);

        $searchResult = Car::search('Ioniq')->get();
        $this->assertTrue($searchResult->contains('id', $car->id));

        $electricCars = Car::byFuelType('electric')->get();
        $this->assertTrue($electricCars->contains('id', $car->id));
    }
}
