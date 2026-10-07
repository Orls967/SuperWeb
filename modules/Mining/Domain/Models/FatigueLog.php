<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FatigueLog extends Model
{
    protected $table = 'min_fatigue_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'work_hours_continuous' => 'float',
        'sleep_hours_prior' => 'float',
        'fatigue_score' => 'integer',
        'is_critical' => 'boolean',
        'logged_at' => 'datetime',
    ];
}
