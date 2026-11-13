<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $table = 'sct_alert_rules';

    protected $fillable = [
        'rule_code',
        'category',
        'severity',
        'threshold_bps',
        'is_active',
    ];

    protected $casts = [
        'threshold_bps' => 'integer',
        'is_active' => 'boolean',
    ];
}
