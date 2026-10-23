<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models\Autonomous;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DroneMission extends Model
{
    protected $table = 'lgx_drone_missions';

    protected $fillable = [
        'mission_code',
        'drone_unit_id',
        'shipment_code',
        'package_weight_kg',
        'distance_km',
        'destination_geo_hash',
        'status',
        'pod_signature_hash',
        'delivered_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];

    public function drone(): BelongsTo
    {
        return $this->belongsTo(DroneUnit::class, 'drone_unit_id');
    }
}
