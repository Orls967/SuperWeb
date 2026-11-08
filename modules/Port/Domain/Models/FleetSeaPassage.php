<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetSeaPassage extends Model
{
    protected $table = 'prt_fleet_sea_passages';

    protected $fillable = [
        'voyage_id',
        'passage_plan_ref',
        'waypoints',
        'under_keel_clearance_min_m',
        'ecdis_cross_track_limit_checked',
        'marpol_special_area_transit',
        'seca_transit',
    ];

    protected $casts = [
        'waypoints' => 'array',
        'under_keel_clearance_min_m' => 'float',
        'ecdis_cross_track_limit_checked' => 'boolean',
        'marpol_special_area_transit' => 'boolean',
        'seca_transit' => 'boolean',
    ];

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(FleetVoyage::class, 'voyage_id');
    }
}
