<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetPilotageRequest extends Model
{
    protected $table = 'prt_fleet_pilotage_requests';

    protected $fillable = [
        'port_call_id',
        'service_type',
        'pilot_boarded_at',
        'pilot_disembarked_at',
        'pilot_name',
        'pilotage_fee_usd',
        'status',
    ];

    protected $casts = [
        'pilot_boarded_at' => 'datetime',
        'pilot_disembarked_at' => 'datetime',
        'pilotage_fee_usd' => 'float',
    ];

    public function portCall(): BelongsTo
    {
        return $this->belongsTo(FleetPortCall::class, 'port_call_id');
    }
}
