<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RiskHeatmapAlert extends Model
{
    protected $table = 'min_risk_heatmap_alerts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'threshold_pct' => 'float',
        'actual_variance_pct' => 'float',
        'c_suite_notified' => 'boolean',
        'triggered_at' => 'datetime',
    ];
}
