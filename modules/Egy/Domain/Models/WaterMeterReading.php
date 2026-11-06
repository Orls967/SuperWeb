<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WaterMeterReading extends Model
{
    protected $table = 'egy_water_meter_readings';

    protected $fillable = [
        'network_id',
        'node_id',
        'meter_id',
        'flow_rate_lps',
        'cumulative_volume_l',
        'pressure_kpa',
        'recorded_at',
    ];

    protected $casts = [
        'flow_rate_lps' => 'integer',
        'cumulative_volume_l' => 'integer',
        'pressure_kpa' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(WaterNetwork::class, 'network_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(WaterNode::class, 'node_id');
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(SmartMeter::class, 'meter_id');
    }
}
