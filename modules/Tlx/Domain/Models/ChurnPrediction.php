<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ChurnPrediction extends Model
{
    protected $table = 'tlx_churn_predictions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'subscriber_id',
        'subscriber_type',
        'usage_decline_pct',
        'support_tickets_count',
        'churn_risk_score',
        'risk_level',
        'recommended_action',
    ];

    protected $casts = [
        'usage_decline_pct' => 'float',
        'support_tickets_count' => 'integer',
        'churn_risk_score' => 'float',
    ];
}
