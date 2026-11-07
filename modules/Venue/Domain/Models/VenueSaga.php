<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VenueSaga extends Model
{
    protected $table = 'ven_sagas';

    protected $fillable = [
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
