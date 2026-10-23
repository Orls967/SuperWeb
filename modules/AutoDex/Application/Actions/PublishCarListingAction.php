<?php

declare(strict_types=1);

namespace Modules\AutoDex\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Shared\Application\BaseAction;

class PublishCarListingAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): Car
    {
        return DB::transaction(function () use ($params) {
            $brandId = (string) $params['brand_id'];
            Brand::findOrFail($brandId);

            $model = (string) $params['model'];
            $yearStart = (int) $params['year_start'];
            $slug = (string) ($params['slug'] ?? Str::slug("{$model}-{$yearStart}-".Str::random(4)));

            $car = Car::create([
                'brand_id' => $brandId,
                'model' => $model,
                'slug' => $slug,
                'year_start' => $yearStart,
                'year_end' => isset($params['year_end']) ? (int) $params['year_end'] : null,
                'body_type' => (string) ($params['body_type'] ?? 'sedan'),
                'fuel_type' => (string) ($params['fuel_type'] ?? 'gasoline'),

                'engine' => (string) ($params['engine'] ?? '2.0L Turbo'),
                'horsepower' => (int) ($params['horsepower'] ?? 250),
                'torque_nm' => (int) ($params['torque_nm'] ?? 350),
                'transmission' => (string) ($params['transmission'] ?? 'automatic'),
                'drivetrain' => (string) ($params['drivetrain'] ?? 'rwd'),
                'top_speed_kmh' => (int) ($params['top_speed_kmh'] ?? 240),
                'zero_to_100' => (string) ($params['zero_to_100'] ?? '6.2'),
                'range_km' => isset($params['range_km']) ? (int) $params['range_km'] : null,
                'battery_kwh' => isset($params['battery_kwh']) ? (int) $params['battery_kwh'] : null,
                'price_idr' => (int) ($params['price_idr'] ?? 750000000),
                'image_url' => (string) ($params['image_url'] ?? 'https://placehold.co/600x400'),
                'description' => (string) ($params['description'] ?? "High performance {$model}"),
                'is_active' => true,
            ]);

            $this->audit(
                action: 'autodex_car_published',
                auditable: $car,
                newValues: ['model' => $model, 'slug' => $slug, 'price_idr' => $car->price_idr],
                impactType: 'catalog_management',
            );

            return $car;
        });
    }
}
