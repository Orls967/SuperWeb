<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyIncident extends Model
{
    protected $table = 'min_safety_incidents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'leading_indicator_points' => 'integer',
        'is_anonymous' => 'boolean',
        'reported_at' => 'datetime',
    ];
}
