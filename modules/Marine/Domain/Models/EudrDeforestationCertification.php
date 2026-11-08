<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EudrDeforestationCertification extends Model
{
    protected $table = 'mar_eudr_deforestation_certifications';

    protected $fillable = [
        'due_diligence_statement_number',
        'batch_id',
        'pond_polygon_center_lat',
        'pond_polygon_center_lng',
        'geolocation_polygon_geojson',
        'cutoff_baseline_date',
        'zero_deforestation_verified',
        'indigenous_customary_rights_respected',
        'satellite_canopy_sensor',
        'certification_verdict',
    ];

    protected $casts = [
        'geolocation_polygon_geojson' => 'array',
        'zero_deforestation_verified' => 'boolean',
        'indigenous_customary_rights_respected' => 'boolean',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AquacultureBatch::class, 'batch_id');
    }
}
