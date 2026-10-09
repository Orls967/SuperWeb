<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SimTwinState extends Model
{
    protected $table = 'sim_twin_states';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'state',
        'state_hash',
        'prev_hash',
        'valid_from',
        'is_sandbox',
    ];

    protected $casts = [
        'state' => 'array',
        'valid_from' => 'datetime',
        'is_sandbox' => 'boolean',
    ];
}
