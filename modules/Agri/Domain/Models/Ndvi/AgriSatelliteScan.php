<?php

declare(strict_types=1);

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;

class AgriSatelliteScan extends Model
{
    protected $table = 'agri_satellite_scans';

    protected $fillable = [
        'scan_code',
        'plot_code',
        'scan_date',
        'ndvi_score',
        'crop_health_status',
        'polygon_geojson',
        'idempotency_key',
    ];

    protected $casts = [
        'scan_date' => 'date',
        'ndvi_score' => 'decimal:3',
        'polygon_geojson' => 'array',
    ];
}
