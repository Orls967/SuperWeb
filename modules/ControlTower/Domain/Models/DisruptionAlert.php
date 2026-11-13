<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DisruptionAlert extends Model
{
    protected $table = 'sct_disruption_alerts';

    protected $fillable = [
        'alert_code',
        'dedup_key',
        'severity',
        'category',
        'title',
        'description',
        'affected_orders_count',
        'status',
        'mitigation_proposal_json',
        'approved_by_user_id',
    ];

    protected $casts = [
        'affected_orders_count' => 'integer',
        'mitigation_proposal_json' => 'array',
        'approved_by_user_id' => 'integer',
    ];
}
