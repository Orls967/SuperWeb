<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Cross-module query directory for vehicle snapshots (DR4-4, W8-D44).
 * Eliminates direct domain model coupling from business modules.
 */
interface VehicleDirectory
{
    /**
     * Find a vehicle snapshot by its primary ID.
     */
    public function findById(int $id): ?VehicleSnapshot;

    /**
     * Find a vehicle snapshot by its normalized plate number.
     */
    public function findByPlateNumber(string $plateNumber): ?VehicleSnapshot;

    /**
     * Find a vehicle snapshot by its VIN.
     */
    public function findByVin(string $vin): ?VehicleSnapshot;

    /**
     * Retrieve multiple snapshots by plate numbers.
     *
     * @param  list<string>  $plateNumbers
     * @return list<VehicleSnapshot>
     */
    public function snapshotsByPlateNumbers(array $plateNumbers): array;

    /**
     * Count vehicles owned by a specific user.
     */
    public function countByOwner(int $userId): int;

    /**
     * Retrieve all active vehicle VIN strings.
     *
     * @return list<string>
     */
    public function activeVinList(): array;

    /**
     * Retrieve multiple vehicle snapshots by IDs.
     *
     * @param  list<int>  $ids
     * @return list<VehicleSnapshot>
     */
    public function snapshotsByIds(array $ids): array;

    /**
     * Retrieve active vehicle snapshots owned by a user.
     *
     * @return list<VehicleSnapshot>
     */
    public function activeForUser(int $userId): array;

    /**
     * Search vehicle snapshots matching criteria.
     *
     * @return list<VehicleSnapshot>
     */
    public function search(VehicleSearchCriteria $criteria): array;

    /**
     * Compute aggregated fleet summary metrics.
     */
    public function getFleetSummary(): VehicleFleetSummary;
}
