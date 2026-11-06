<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LeakIncident extends Model
{
    protected $table = 'egy_leak_incidents';

    protected $fillable = [
        'network_id',
        'zone_code',
        'detected_at',
        'severity',
        'estimated_leak_rate_lps',
        'inlet_volume_l',
        'billed_volume_l',
        'loss_percentage_bps',
        'asset_work_order_id',
        'status',
        'description',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'estimated_leak_rate_lps' => 'integer',
        'inlet_volume_l' => 'integer',
        'billed_volume_l' => 'integer',
        'loss_percentage_bps' => 'integer',
        'asset_work_order_id' => 'integer',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(WaterNetwork::class, 'network_id');
    }
}
