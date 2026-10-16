<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DisruptionAlert extends Model
{
    protected $table = 'sct_disruption_alerts';

    protected $fillable = [
        'alert_code',
        'severity',
        'category',
        'title',
        'description',
        'affected_orders_count',
        'status',
    ];

    protected $casts = [
        'affected_orders_count' => 'integer',
    ];
}
