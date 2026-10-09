<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SimEventSpine extends Model
{
    protected $table = 'sim_event_spine';

    protected $fillable = [
        'event_id',
        'topic',
        'event_name',
        'version',
        'payload',
        'idempotency_key',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'version' => 'integer',
    ];
}
