<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Services;

use DomainException;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;

class KitchenStationService
{
    /**
     * Register a new ghost kitchen station facility.
     */
    public function registerKitchen(array $data): CloudKitchen
    {
        return CloudKitchen::create([
            'kitchen_code' => $data['kitchen_code'],
            'name' => $data['name'],
            'type' => $data['type'] ?? 'satellite',
            'hourly_capacity' => $data['hourly_capacity'] ?? 50,
            'current_hourly_load' => 0,
            'service_radius_km' => $data['service_radius_km'] ?? 10.0,
            'is_active' => true,
        ]);
    }

    /**
     * Find active kitchen with available capacity headroom, prioritizing lowest load.
     *
     * @throws DomainException
     */
    public function findAvailableKitchenWithCapacity(): CloudKitchen
    {
        $kitchen = CloudKitchen::where('is_active', true)
            ->whereRaw('current_hourly_load < hourly_capacity')
            ->orderBy('current_hourly_load', 'asc')
            ->first();

        if (! $kitchen) {
            throw new DomainException('No cloud kitchen currently has capacity available.');
        }

        return $kitchen;
    }

    /**
     * Release hourly capacity load from a kitchen station.
     */
    public function releaseKitchenCapacity(CloudKitchen $kitchen): void
    {
        if ($kitchen->current_hourly_load > 0) {
            $kitchen->decrement('current_hourly_load');
        }
    }
}
