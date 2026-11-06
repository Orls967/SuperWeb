<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WaterNode extends Model
{
    protected $table = 'egy_water_nodes';

    protected $fillable = [
        'network_id',
        'node_code',
        'node_type',
        'elevation_m',
        'normal_pressure_kpa',
        'status',
    ];

    protected $casts = [
        'elevation_m' => 'integer',
        'normal_pressure_kpa' => 'integer',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(WaterNetwork::class, 'network_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(WaterMeterReading::class, 'node_id');
    }
}
