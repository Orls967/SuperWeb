<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
        'seed',
        'metrics',
    ];

    protected $casts = [
        'virtual_now' => 'datetime',
        'sim_start_at' => 'datetime',
        'sim_end_at' => 'datetime',
        'speed_multiplier' => 'integer',
        'seed' => 'integer',
        'metrics' => 'array',
    ];
}
