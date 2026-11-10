<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimTwinSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sim_twin_snapshots';

    protected $fillable = [
        'run_id',
        'day_number',
        'entity_type',
        'entity_id',
        'state_hash',
        'state_payload',
        'created_at',
    ];

    protected $casts = [
        'day_number' => 'integer',
        'state_payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SimRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(SimRun::class, 'run_id');
    }
}
