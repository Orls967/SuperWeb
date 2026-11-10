<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimRun extends Model
{
    use HasUuids;

    protected $table = 'sim_runs';

    protected $fillable = [
        'id',
        'name',
        'virtual_now',
        'sim_start_at',
        'sim_end_at',
        'speed_multiplier',
        'status',
        'mode',
        'seed',
        'current_day',
        'total_days',
        'chain_hash',
        'last_completed_day',
        'last_state_hash',
        'created_by',
        'completed_at',
        'metrics',
    ];

    protected $casts = [
        'virtual_now' => 'datetime',
        'sim_start_at' => 'datetime',
        'sim_end_at' => 'datetime',
        'speed_multiplier' => 'integer',
        'seed' => 'integer',
        'current_day' => 'integer',
        'total_days' => 'integer',
        'last_completed_day' => 'integer',
        'created_by' => 'integer',
        'completed_at' => 'datetime',
        'metrics' => 'array',
    ];

    /**
     * @return HasMany<SimCheckpoint, $this>
     */
    public function checkpoints(): HasMany
    {
        return $this->hasMany(SimCheckpoint::class, 'run_id');
    }

    /**
     * @return HasMany<SimRunTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(SimRunTransition::class, 'run_id')->orderBy('id', 'asc');
    }

    /**
     * @return HasMany<SimTwinSnapshot, $this>
     */
    public function twinSnapshots(): HasMany
    {
        return $this->hasMany(SimTwinSnapshot::class, 'run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
