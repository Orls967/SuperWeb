<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MiningSaga extends Model
{
    use HasUuids;

    protected $table = 'min_mining_sagas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'saga_type',
        'saga_reference',
        'current_step',
        'status',
        'payload_json',
        'compensation_log_json',
        'completed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'compensation_log_json' => 'array',
        'completed_at' => 'datetime',
    ];
}
