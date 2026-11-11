<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Repositories;

use Modules\Core\Contracts\Vehicle;
use Modules\Core\Contracts\VehicleDirectory;
use Modules\Core\Contracts\VehicleFleetSummary;
use Modules\Core\Contracts\VehicleSearchCriteria;
use Modules\Core\Contracts\VehicleSnapshot;

final class EloquentVehicleDirectory implements VehicleDirectory
{
    public function findById(int $id): ?VehicleSnapshot
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = Vehicle::find($id);

        return $vehicle !== null ? $this->toSnapshot($vehicle) : null;
    }

    public function findByPlateNumber(string $plateNumber): ?VehicleSnapshot
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', '', $plateNumber) ?? ''));

        /** @var Vehicle|null $vehicle */
        $vehicle = Vehicle::whereRaw("REPLACE(UPPER(plate_number), ' ', '') = ?", [$normalized])->first();

        return $vehicle !== null ? $this->toSnapshot($vehicle) : null;
    }

    public function findByVin(string $vin): ?VehicleSnapshot
    {
        $normalized = strtoupper(trim($vin));

        /** @var Vehicle|null $vehicle */
        $vehicle = Vehicle::whereRaw('UPPER(vin) = ?', [$normalized])->first();

        return $vehicle !== null ? $this->toSnapshot($vehicle) : null;
    }

    public function snapshotsByPlateNumbers(array $plateNumbers): array
    {
        if ($plateNumbers === []) {
            return [];
        }

        $normalizedList = array_map(
            fn (string $p): string => strtoupper(trim(preg_replace('/\s+/', '', $p) ?? '')),
            $plateNumbers
        );

        $vehicles = Vehicle::all()->filter(function (Vehicle $v) use ($normalizedList): bool {
            $p = strtoupper(trim(preg_replace('/\s+/', '', (string) $v->plate_number) ?? ''));

            return in_array($p, $normalizedList, true);
        });

        return $vehicles->map(fn (Vehicle $v): VehicleSnapshot => $this->toSnapshot($v))->values()->all();
    }

    public function snapshotsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $vehicles = Vehicle::whereIn('id', $ids)->get();

        return $vehicles->map(fn (Vehicle $v): VehicleSnapshot => $this->toSnapshot($v))->all();
    }

    public function activeForUser(int $userId): array
    {
        $vehicles = Vehicle::where('user_id', $userId)->where('status', 'active')->get();

        return $vehicles->map(fn (Vehicle $v): VehicleSnapshot => $this->toSnapshot($v))->all();
    }

    public function countByOwner(int $userId): int
    {
        return Vehicle::where('user_id', $userId)->count();
    }

    public function activeVinList(): array
    {
        return Vehicle::where('status', 'active')
            ->whereNotNull('vin')
            ->pluck('vin')
            ->map(fn ($v): string => (string) $v)
            ->all();
    }

    public function search(VehicleSearchCriteria $criteria): array
    {
        $query = Vehicle::query();

        if ($criteria->ids !== null && $criteria->ids !== []) {
            $query->whereIn('id', $criteria->ids);
        }

        if ($criteria->plateNumberPattern !== null && $criteria->plateNumberPattern !== '') {
            $query->where('plate_number', 'like', "%{$criteria->plateNumberPattern}%");
        }

        if ($criteria->vinPattern !== null && $criteria->vinPattern !== '') {
            $query->where('vin', 'like', "%{$criteria->vinPattern}%");
        }

        if ($criteria->statuses !== null && $criteria->statuses !== []) {
            $query->whereIn('status', $criteria->statuses);
        }

        if ($criteria->minOdometerKm !== null) {
            $query->where('odometer_km', '>=', $criteria->minOdometerKm);
        }

        if ($criteria->maxOdometerKm !== null) {
            $query->where('odometer_km', '<=', $criteria->maxOdometerKm);
        }

        if ($criteria->color !== null && $criteria->color !== '') {
            $query->where('color', $criteria->color);
        }

        $vehicles = $query->offset($criteria->offset)
            ->limit($criteria->limit)
            ->get();

        return $vehicles->map(fn (Vehicle $v): VehicleSnapshot => $this->toSnapshot($v))->all();
    }

    public function getFleetSummary(): VehicleFleetSummary
    {
        $total = Vehicle::count();
        $active = Vehicle::where('status', 'active')->count();
        $maintenance = Vehicle::where('status', 'maintenance')->count();
        $inactive = Vehicle::where('status', 'inactive')->count();
        $avgOdometer = (int) round((float) Vehicle::avg('odometer_km'));

        $distributions = Vehicle::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($c): int => (int) $c)
            ->all();

        return new VehicleFleetSummary(
            totalVehicles: $total,
            activeVehicles: $active,
            maintenanceVehicles: $maintenance,
            inactiveVehicles: $inactive,
            averageOdometerKm: $avgOdometer,
            statusDistribution: $distributions,
        );
    }

    private function toSnapshot(Vehicle $vehicle): VehicleSnapshot
    {
        return new VehicleSnapshot(
            id: (int) $vehicle->id,
            uuid: $vehicle->uuid !== null ? (string) $vehicle->uuid : null,
            userId: $vehicle->user_id !== null ? (int) $vehicle->user_id : null,
            carId: $vehicle->car_id !== null ? (int) $vehicle->car_id : null,
            plateNumber: $vehicle->plate_number !== null ? (string) $vehicle->plate_number : null,
            vin: $vehicle->vin !== null ? (string) $vehicle->vin : null,
            color: $vehicle->color !== null ? (string) $vehicle->color : null,
            odometerKm: (int) ($vehicle->odometer_km ?? 0),
            status: (string) ($vehicle->status ?? 'active'),
            acquiredAt: $vehicle->acquired_at?->toIso8601String(),
            passportUrl: method_exists($vehicle, 'getPassportUrl') ? $vehicle->getPassportUrl() : null,
        );
    }
}
