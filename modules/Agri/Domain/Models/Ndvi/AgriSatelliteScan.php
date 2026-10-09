<?php

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;

class AgriSatelliteScan extends Model
{
    protected $table = 'agri_satellite_scans';

    protected $guarded = [];

    protected $casts = [
        'scan_date' => 'date',
        'ndvi_score' => 'decimal:3',
        'polygon_geojson' => 'array',
    ];
}
