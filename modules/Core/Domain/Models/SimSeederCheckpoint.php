<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SimSeederCheckpoint extends Model
{
    protected $table = 'sim_seeder_checkpoints';

    protected $fillable = [
        'seeder_name',
        'stage',
        'last_processed_id',
        'batch_number',
        'metrics',
        'completed',
    ];

    protected $casts = [
        'last_processed_id' => 'integer',
        'batch_number' => 'integer',
        'metrics' => 'array',
        'completed' => 'boolean',
    ];
}
