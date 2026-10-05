<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DisruptionAlert extends Model
{
    protected $table = 'sct_disruption_alerts';

    protected $guarded = [];

    protected $casts = [
        'affected_orders_count' => 'integer',
    ];
}
