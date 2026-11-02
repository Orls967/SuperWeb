<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GeofenceZone extends Model
{
    protected $table = 'oto_geofence_zones';

    protected $fillable = [
        'zone_code',
        'name',
        'polygon_json',
        'min_lat_e6',
        'max_lat_e6',
        'min_lon_e6',
        'max_lon_e6',
        'is_active',
    ];

    protected $casts = [
        'polygon_json' => 'array',
        'min_lat_e6' => 'integer',
        'max_lat_e6' => 'integer',
        'min_lon_e6' => 'integer',
        'max_lon_e6' => 'integer',
        'is_active' => 'boolean',
    ];
}
