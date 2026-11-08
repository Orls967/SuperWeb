<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetBunkeringOperation extends Model
{
    protected $table = 'prt_fleet_bunkering_operations';

    protected $fillable = [
        'vessel_id',
        'port_call_id',
        'bunker_stem_number',
        'supplier_name',
        'barge_name',
        'fuel_grade',
        'quantity_metric_tons',
        'density_at_15c_kg_m3',
        'sulphur_content_percentage',
        'viscosity_cst_at_50c',
        'bunker_delivery_note_number',
        'marpol_seal_number',
        'bunkering_started_at',
        'bunkering_completed_at',
        'compliance_status',
    ];

    protected $casts = [
        'quantity_metric_tons' => 'float',
        'density_at_15c_kg_m3' => 'float',
        'sulphur_content_percentage' => 'float',
        'viscosity_cst_at_50c' => 'float',
        'bunkering_started_at' => 'datetime',
        'bunkering_completed_at' => 'datetime',
    ];

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }

    public function portCall(): BelongsTo
    {
        return $this->belongsTo(FleetPortCall::class, 'port_call_id');
    }
}

