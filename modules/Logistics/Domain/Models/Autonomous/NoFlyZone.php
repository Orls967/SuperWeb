<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models\Autonomous;

use Modules\Logistics\Domain\Models\LogisticsEntity;

class NoFlyZone extends LogisticsEntity
{
    protected $table = 'lgx_no_fly_zones';

    protected $fillable = [
        'zone_code',
        'name',
        'polygon_coordinates_json',
        'min_altitude_meters',
        'max_altitude_meters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_altitude_meters' => 'integer',
            'max_altitude_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<array{x: int, y: int}>
     */
    public function getPolygonCoordinates(): array
    {
        /** @var list<array{x: int, y: int}> $coords */
        $coords = json_decode($this->polygon_coordinates_json, true) ?? [];

        return $coords;
    }
}
