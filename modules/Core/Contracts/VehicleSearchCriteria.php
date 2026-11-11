<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Value Object for querying vehicles across modules without direct Eloquent coupling.
 */
final class VehicleSearchCriteria
{
    /**
     * @param  list<int|string>|null  $ids
     * @param  list<string>|null  $statuses
     */
    public function __construct(
        public readonly ?array $ids = null,
        public readonly ?string $plateNumberPattern = null,
        public readonly ?string $vinPattern = null,
        public readonly ?array $statuses = null,
        public readonly ?int $minOdometerKm = null,
        public readonly ?int $maxOdometerKm = null,
        public readonly ?string $color = null,
        public readonly int $limit = 50,
        public readonly int $offset = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ids: isset($data['ids']) && is_array($data['ids']) ? $data['ids'] : null,
            plateNumberPattern: isset($data['plate_number']) ? (string) $data['plate_number'] : null,
            vinPattern: isset($data['vin']) ? (string) $data['vin'] : null,
            statuses: isset($data['statuses']) && is_array($data['statuses']) ? $data['statuses'] : null,
            minOdometerKm: isset($data['min_odometer']) ? (int) $data['min_odometer'] : null,
            maxOdometerKm: isset($data['max_odometer']) ? (int) $data['max_odometer'] : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
            limit: isset($data['limit']) ? (int) $data['limit'] : 50,
            offset: isset($data['offset']) ? (int) $data['offset'] : 0,
        );
    }
}

