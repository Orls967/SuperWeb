<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetTugService extends Model
{
    protected $table = 'prt_fleet_tug_services';

    protected $fillable = [
        'port_call_id',
        'tug_name',
        'bollard_pull_tonnage',
        'hours_used',
        'hourly_rate_usd',
        'total_cost_usd',
        'service_phase',
    ];

    protected $casts = [
        'bollard_pull_tonnage' => 'integer',
        'hours_used' => 'integer',
        'hourly_rate_usd' => 'float',
        'total_cost_usd' => 'float',
    ];

    public function portCall(): BelongsTo
    {
        return $this->belongsTo(FleetPortCall::class, 'port_call_id');
    }
}

