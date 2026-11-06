<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandResponseAllocation extends Model
{
    use HasUuids;

    protected $table = 'egy_demand_response_allocations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'demand_response_event_id',
        'beneficiary_party_id',
        'proportion_bps',
        'allocated_reward_idr',
        'status',
    ];

    protected $casts = [
        'proportion_bps' => 'integer',
        'allocated_reward_idr' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(DemandResponseEvent::class, 'demand_response_event_id');
    }
}
