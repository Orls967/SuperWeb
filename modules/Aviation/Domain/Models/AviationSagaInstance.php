<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AviationSagaInstance extends Model
{
    protected $table = 'avi_saga_instances';

    protected $fillable = [
        'saga_type',
        'correlation_id',
        'current_step',
        'status',
        'payload_json',
        'log_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'log_json' => 'array',
    ];
}

