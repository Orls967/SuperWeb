<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimCheckpoint extends Model
{
    protected $table = 'sim_checkpoints';

    protected $fillable = [
        'run_id',
        'day_number',
        'chain_hash',
        'handler_hashes',
        'state_summary',
    ];

    protected $casts = [
        'day_number' => 'integer',
        'handler_hashes' => 'array',
        'state_summary' => 'array',
    ];

    /**
     * @return BelongsTo<SimRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(SimRun::class, 'run_id');
    }
}
