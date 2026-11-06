<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandResponseEvent extends Model
{
    use HasUuids;

    protected $table = 'egy_demand_response_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_code',
        'site_asset_id',
        'event_date',
        'peak_window_start',
        'peak_window_end',
        'shifted_load_kwh',
        'cost_savings_idr',
        'total_reward_idr',
        'emission_reduction_g_co2e',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'site_asset_id' => 'integer',
        'event_date' => 'date',
        'peak_window_start' => 'datetime',
        'peak_window_end' => 'datetime',
        'shifted_load_kwh' => 'integer',
        'cost_savings_idr' => 'integer',
        'total_reward_idr' => 'integer',
        'emission_reduction_g_co2e' => 'integer',
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(DemandResponseAllocation::class, 'demand_response_event_id');
    }
}
