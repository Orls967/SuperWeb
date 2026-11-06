<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchRun extends Model
{
    use HasUuids;

    protected $table = 'egy_dispatch_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'run_number',
        'hour_timestamp',
        'load_mw',
        'total_dispatched_mw',
        'total_curtailed_mw',
        'total_cost_thousand_idr',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'hour_timestamp' => 'datetime',
        'load_mw' => 'integer',
        'total_dispatched_mw' => 'integer',
        'total_curtailed_mw' => 'integer',
        'total_cost_thousand_idr' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(DispatchOrder::class, 'dispatch_run_id');
    }
}
